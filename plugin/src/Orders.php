<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

use Nimbus\Plugin\PluginStorage;

/**
 * Orders — the heart of service. An order opens on a table and collects line items,
 * each **snapshotting** the item's name and unit price at order time so a later menu
 * edit never rewrites the bill. The order total is always **computed** from its
 * lines, never stored or trusted from a client (the security review's rule for
 * money). The write discipline is the CRM/Tables one: field allow-lists, a
 * write-time status allow-list ({@see STATUSES}), bound SQL, and total,
 * transactional deletes (an order's lines go with it).
 *
 * The menu lives in a Nimbus collection, not this plugin, so Orders never reads it
 * directly: it takes a **snapshot resolver** — `fn(int $menuItemId): ?array{name,price}`
 * — which the plugin backs with {@see Menu} (over the core content-read capability),
 * and a test backs with a fake. Orders therefore has no dependency on core content
 * internals.
 */
final class Orders
{
    /** @var list<string> the order workflow, in order */
    public const STATUSES = ['open', 'sent', 'preparing', 'ready', 'served', 'closed'];

    private const MAX_NAME = 200;
    private const MAX_QTY  = 999;

    /**
     * @param \Closure():PluginStorage        $storage  resolved lazily
     * @param \Closure(int):(array{name:string,price:string}|null) $snapshot menu-item resolver
     */
    public function __construct(
        private \Closure $storage,
        private Tables $tables,
        private \Closure $snapshot,
    ) {
    }

    /**
     * Open an order on a table (which the guests are now seated at, so the table
     * becomes occupied) — atomically. Returns the new order id.
     */
    public function open(int $tableId, string $now): int
    {
        if ($this->tables->get($tableId) === null) {
            throw new \InvalidArgumentException("No table with id {$tableId}.");
        }
        return (int) $this->storage()->transaction(function () use ($tableId, $now): int {
            $id = $this->storage()->insert(
                'INSERT INTO ' . Schema::ORDER . ' (table_id, status, paid, created_at, updated_at)
                 VALUES (:table, :status, 0, :created, :updated)',
                ['table' => $tableId, 'status' => 'open', 'created' => $now, 'updated' => $now],
            );
            $this->tables->setStatus($tableId, 'occupied', $now);
            return $id;
        });
    }

    /**
     * One order with its line items and computed total, or null.
     *
     * @return array{id:int,table_id:int,table_label:?string,status:string,paid:bool,items:list<array{id:int,menu_item_id:?int,name:string,unit_price:string,qty:int,line_total:string}>,total:string,created_at:string,updated_at:string}|null
     */
    public function get(int $id): ?array
    {
        $row = $this->storage()->selectOne(
            'SELECT o.id, o.table_id, o.status, o.paid, o.created_at, o.updated_at, t.label AS table_label
             FROM ' . Schema::ORDER . ' o LEFT JOIN ' . Schema::TABLE . ' t ON t.id = o.table_id
             WHERE o.id = :id',
            ['id' => $id],
        );
        if ($row === null) {
            return null;
        }
        $items = $this->items($id);
        return $this->hydrate($row, $items);
    }

    /**
     * Orders for the list / MCP, optionally filtered by allow-listed status and/or
     * table, each with its computed total and item count (no line items — get() has
     * those). Most-recently-updated first.
     *
     * @return list<array{id:int,table_id:int,table_label:?string,status:string,paid:bool,item_count:int,total:string,created_at:string,updated_at:string}>
     */
    public function all(?string $status = null, ?int $tableId = null): array
    {
        $where  = [];
        $params = [];
        if ($status !== null && $status !== '' && in_array($status, self::STATUSES, true)) {
            $where[]          = 'o.status = :status';
            $params['status'] = $status;
        }
        if ($tableId !== null) {
            $where[]         = 'o.table_id = :table';
            $params['table'] = $tableId;
        }

        $sql = 'SELECT o.id, o.table_id, o.status, o.paid, o.created_at, o.updated_at, t.label AS table_label,
                       (SELECT COALESCE(SUM(i.unit_price * i.qty), 0) FROM ' . Schema::ORDER_ITEM . ' i WHERE i.order_id = o.id) AS total,
                       (SELECT COUNT(*) FROM ' . Schema::ORDER_ITEM . ' i WHERE i.order_id = o.id) AS item_count
                FROM ' . Schema::ORDER . ' o LEFT JOIN ' . Schema::TABLE . ' t ON t.id = o.table_id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY o.updated_at DESC, o.id DESC';

        return array_map(function (array $r): array {
            return [
                'id'          => (int) $r['id'],
                'table_id'    => (int) $r['table_id'],
                'table_label' => ($r['table_label'] ?? null) === null ? null : (string) $r['table_label'],
                'status'      => (string) $r['status'],
                'paid'        => (bool) $r['paid'],
                'item_count'  => (int) $r['item_count'],
                'total'       => number_format((float) $r['total'], 2, '.', ''),
                'created_at'  => (string) $r['created_at'],
                'updated_at'  => (string) $r['updated_at'],
            ];
        }, $this->storage()->select($sql, $params));
    }

    /** Move an order to an allow-listed workflow status. Returns rows changed. */
    public function setStatus(int $id, string $status, string $now): int
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('"status" must be one of: ' . implode(', ', self::STATUSES) . '.');
        }
        return $this->storage()->execute(
            'UPDATE ' . Schema::ORDER . ' SET status = :status, updated_at = :now WHERE id = :id',
            ['status' => $status, 'now' => $now, 'id' => $id],
        );
    }

    /**
     * Add a line to an order. Give a `menuItemId` to add from the menu (its name +
     * price are snapshotted via the resolver), or a `name` + `price` for a manual
     * line. Returns the new line id.
     */
    public function addItem(int $orderId, ?int $menuItemId, ?string $name, ?string $price, int $qty, string $now): int
    {
        if ($this->orderExists($orderId) === false) {
            throw new \InvalidArgumentException("No order with id {$orderId}.");
        }
        if ($qty < 1 || $qty > self::MAX_QTY) {
            throw new \InvalidArgumentException('"qty" must be a whole number between 1 and ' . self::MAX_QTY . '.');
        }

        if ($menuItemId !== null) {
            $snap = ($this->snapshot)($menuItemId);
            if ($snap === null) {
                throw new \InvalidArgumentException("No menu item with id {$menuItemId}.");
            }
            $lineName  = $snap['name'];
            $linePrice = $snap['price'];
        } else {
            $lineName  = $this->name($name);
            $linePrice = $this->price($price);
        }

        return (int) $this->storage()->transaction(function () use ($orderId, $menuItemId, $lineName, $linePrice, $qty, $now): int {
            $lineId = $this->storage()->insert(
                'INSERT INTO ' . Schema::ORDER_ITEM . ' (order_id, menu_item_id, name, unit_price, qty, created_at)
                 VALUES (:order, :menu, :name, :price, :qty, :created)',
                ['order' => $orderId, 'menu' => $menuItemId, 'name' => $lineName, 'price' => $linePrice, 'qty' => $qty, 'created' => $now],
            );
            $this->touch($orderId, $now);
            return $lineId;
        });
    }

    /** Change a line's quantity; a quantity of 0 removes it. Returns rows affected. */
    public function setItemQty(int $itemId, int $qty, string $now): int
    {
        if ($qty < 0 || $qty > self::MAX_QTY) {
            throw new \InvalidArgumentException('"qty" must be a whole number between 0 and ' . self::MAX_QTY . '.');
        }
        $orderId = $this->orderIdOfItem($itemId);
        if ($orderId === null) {
            return 0;
        }
        if ($qty === 0) {
            return $this->removeItem($itemId);
        }
        $n = $this->storage()->execute(
            'UPDATE ' . Schema::ORDER_ITEM . ' SET qty = :qty WHERE id = :id',
            ['qty' => $qty, 'id' => $itemId],
        );
        $this->touch($orderId, $now);
        return $n;
    }

    /** Remove a line item. Returns rows removed. */
    public function removeItem(int $itemId): int
    {
        return $this->storage()->execute('DELETE FROM ' . Schema::ORDER_ITEM . ' WHERE id = :id', ['id' => $itemId]);
    }

    /** Delete an order and its line items, atomically. Returns order rows removed. */
    public function delete(int $id): int
    {
        return (int) $this->storage()->transaction(function () use ($id): int {
            $this->storage()->execute('DELETE FROM ' . Schema::ORDER_ITEM . ' WHERE order_id = :id', ['id' => $id]);
            return $this->storage()->execute('DELETE FROM ' . Schema::ORDER . ' WHERE id = :id', ['id' => $id]);
        });
    }

    // --- internals -------------------------------------------------------

    /**
     * @return list<array{id:int,menu_item_id:?int,name:string,unit_price:string,qty:int,line_total:string}>
     */
    private function items(int $orderId): array
    {
        $rows = $this->storage()->select(
            'SELECT id, menu_item_id, name, unit_price, qty FROM ' . Schema::ORDER_ITEM . ' WHERE order_id = :id ORDER BY id',
            ['id' => $orderId],
        );
        return array_map(static function (array $r): array {
            $unit = (float) $r['unit_price'];
            $qty  = (int) $r['qty'];
            return [
                'id'           => (int) $r['id'],
                'menu_item_id' => $r['menu_item_id'] === null ? null : (int) $r['menu_item_id'],
                'name'         => (string) $r['name'],
                'unit_price'   => number_format($unit, 2, '.', ''),
                'qty'          => $qty,
                'line_total'   => number_format($unit * $qty, 2, '.', ''),
            ];
        }, $rows);
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array{id:int,menu_item_id:?int,name:string,unit_price:string,qty:int,line_total:string}> $items
     * @return array{id:int,table_id:int,table_label:?string,status:string,paid:bool,items:list<array{id:int,menu_item_id:?int,name:string,unit_price:string,qty:int,line_total:string}>,total:string,created_at:string,updated_at:string}
     */
    private function hydrate(array $row, array $items): array
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += (float) $item['line_total'];
        }
        return [
            'id'          => (int) $row['id'],
            'table_id'    => (int) $row['table_id'],
            'table_label' => ($row['table_label'] ?? null) === null ? null : (string) $row['table_label'],
            'status'      => (string) $row['status'],
            'paid'        => (bool) $row['paid'],
            'items'       => $items,
            'total'       => number_format($total, 2, '.', ''),
            'created_at'  => (string) $row['created_at'],
            'updated_at'  => (string) $row['updated_at'],
        ];
    }

    private function orderExists(int $id): bool
    {
        return $this->storage()->selectOne('SELECT id FROM ' . Schema::ORDER . ' WHERE id = :id', ['id' => $id]) !== null;
    }

    private function orderIdOfItem(int $itemId): ?int
    {
        $row = $this->storage()->selectOne('SELECT order_id FROM ' . Schema::ORDER_ITEM . ' WHERE id = :id', ['id' => $itemId]);
        return $row === null ? null : (int) $row['order_id'];
    }

    private function touch(int $orderId, string $now): void
    {
        $this->storage()->execute('UPDATE ' . Schema::ORDER . ' SET updated_at = :now WHERE id = :id', ['now' => $now, 'id' => $orderId]);
    }

    private function name(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            throw new \InvalidArgumentException('A manual line needs a name.');
        }
        if (mb_strlen($name) > self::MAX_NAME) {
            throw new \InvalidArgumentException('An item name must be ' . self::MAX_NAME . ' characters or fewer.');
        }
        return $name;
    }

    private function price(?string $price): string
    {
        $raw = str_replace([',', ' '], '', trim((string) $price));
        if ($raw === '' || preg_match('/^\d{1,8}(\.\d{1,2})?$/', $raw) !== 1) {
            throw new \InvalidArgumentException('A manual line needs a non-negative price with up to two decimals.');
        }
        return number_format((float) $raw, 2, '.', '');
    }

    private function storage(): PluginStorage
    {
        return ($this->storage)();
    }
}
