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

    /** @var list<string> how a bill can be settled */
    public const PAYMENT_METHODS = ['cash', 'card', 'other'];

    private const MAX_NAME = 200;
    private const MAX_QTY  = 999;

    /** Most distinct lines a single online order may carry (anti-abuse cap). */
    public const MAX_ONLINE_LINES = 40;

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
     * Place an ONLINE (takeaway) order: a table-less order that goes straight to the
     * kitchen queue as already paid — a SIMULATED checkout (no real payment, no
     * processor, no card data; `payment_method` is `online-demo`). The client sends
     * only `{menu_item_id, qty}` per line; the **name and unit price are snapshotted
     * from the published menu** (ADR 0029) and the **total is computed server-side**,
     * so a client can never dictate prices or the amount. Unknown/unpublished items
     * are dropped; qty and line-count are capped; a name and phone are required.
     *
     * @param  list<array{menu_item_id:int,qty:int}> $cart
     * @return array{id:int,table_id:?int,channel:string,customer_name:?string,customer_phone:?string,table_label:?string,status:string,paid:bool,amount_paid:?string,payment_method:?string,paid_at:?string,items:list<array{id:int,menu_item_id:?int,name:string,unit_price:string,qty:int,line_total:string}>,total:string,created_at:string,updated_at:string}
     */
    public function placeOnline(array $cart, string $name, string $phone, string $now): array
    {
        $name  = trim($name);
        $phone = trim($phone);
        if ($name === '' || $phone === '') {
            throw new \InvalidArgumentException('A name and a phone number are required.');
        }
        $name  = mb_substr($name, 0, 120);
        $phone = mb_substr($phone, 0, 40);

        // Build the lines from the menu — snapshot name + price, never trust the
        // client's prices; drop anything not live-published; cap qty and line count.
        $lines = [];
        foreach ($cart as $entry) {
            if (count($lines) >= self::MAX_ONLINE_LINES) {
                break;
            }
            $menuItemId = (int) $entry['menu_item_id'];
            $qty        = (int) $entry['qty'];
            if ($menuItemId <= 0 || $qty < 1) {
                continue;
            }
            $qty  = min($qty, self::MAX_QTY);
            $snap = ($this->snapshot)($menuItemId);
            if ($snap === null) {
                continue; // unknown or unpublished menu item — dropped
            }
            $lines[] = ['menu_item_id' => $menuItemId, 'name' => $snap['name'], 'price' => $snap['price'], 'qty' => $qty];
        }
        if ($lines === []) {
            throw new \InvalidArgumentException('Your order is empty.');
        }

        $total = 0.0;
        foreach ($lines as $line) {
            $total += (float) $line['price'] * $line['qty'];
        }
        $amount = number_format($total, 2, '.', '');

        // A random confirmation token so the confirmation page is not enumerable by
        // order id — a visitor can only see the order they just placed.
        $token = bin2hex(random_bytes(8));

        $orderId = (int) $this->storage()->transaction(function () use ($lines, $name, $phone, $amount, $token, $now): int {
            $id = $this->storage()->insert(
                'INSERT INTO ' . Schema::ORDER . ' (table_id, channel, customer_name, customer_phone, confirm_token, status, paid, amount_paid, payment_method, paid_at, created_at, updated_at)
                 VALUES (NULL, :channel, :cname, :cphone, :token, :status, 1, :amount, :method, :paid_at, :created, :updated)',
                ['channel' => 'online', 'cname' => $name, 'cphone' => $phone, 'token' => $token, 'status' => 'sent', 'amount' => $amount, 'method' => 'online-demo', 'paid_at' => $now, 'created' => $now, 'updated' => $now],
            );
            foreach ($lines as $line) {
                $this->storage()->insert(
                    'INSERT INTO ' . Schema::ORDER_ITEM . ' (order_id, menu_item_id, name, unit_price, qty, created_at)
                     VALUES (:order, :menu, :name, :price, :qty, :created)',
                    ['order' => $id, 'menu' => $line['menu_item_id'], 'name' => $line['name'], 'price' => $line['price'], 'qty' => $line['qty'], 'created' => $now],
                );
            }
            return $id;
        });

        $order = $this->get($orderId);
        assert($order !== null);
        return $order;
    }

    /** The confirmation token for an order (for building its confirmation URL), or null. */
    public function confirmToken(int $orderId): ?string
    {
        $row = $this->storage()->selectOne(
            'SELECT confirm_token FROM ' . Schema::ORDER . ' WHERE id = :id',
            ['id' => $orderId],
        );
        return $row === null || ($row['confirm_token'] ?? null) === null ? null : (string) $row['confirm_token'];
    }

    /**
     * One ONLINE order for its confirmation page — returned only when the order is
     * online AND the supplied token matches (constant-time), so the page cannot be
     * enumerated by id and never reveals a dine-in or another guest's order.
     *
     * @return array{id:int,table_id:?int,channel:string,customer_name:?string,customer_phone:?string,table_label:?string,status:string,paid:bool,amount_paid:?string,payment_method:?string,paid_at:?string,items:list<array{id:int,menu_item_id:?int,name:string,unit_price:string,qty:int,line_total:string}>,total:string,created_at:string,updated_at:string}|null
     */
    public function onlineForConfirmation(int $orderId, string $token): ?array
    {
        $expected = $this->confirmToken($orderId);
        if ($expected === null || $token === '' || !hash_equals($expected, $token)) {
            return null;
        }
        $order = $this->get($orderId);
        return ($order !== null && $order['channel'] === 'online') ? $order : null;
    }

    /**
     * One order with its line items and computed total, or null.
     *
     * @return array{id:int,table_id:?int,channel:string,customer_name:?string,customer_phone:?string,table_label:?string,status:string,paid:bool,amount_paid:?string,payment_method:?string,paid_at:?string,items:list<array{id:int,menu_item_id:?int,name:string,unit_price:string,qty:int,line_total:string}>,total:string,created_at:string,updated_at:string}|null
     */
    public function get(int $id): ?array
    {
        $row = $this->storage()->selectOne(
            'SELECT o.id, o.table_id, o.channel, o.customer_name, o.customer_phone, o.status, o.paid, o.amount_paid, o.payment_method, o.paid_at, o.created_at, o.updated_at, t.label AS table_label
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
     * @return list<array{id:int,table_id:?int,channel:string,customer_name:?string,table_label:?string,status:string,paid:bool,amount_paid:?string,payment_method:?string,item_count:int,total:string,created_at:string,updated_at:string}>
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

        $sql = 'SELECT o.id, o.table_id, o.channel, o.customer_name, o.status, o.paid, o.amount_paid, o.payment_method, o.created_at, o.updated_at, t.label AS table_label,
                       (SELECT COALESCE(SUM(i.unit_price * i.qty), 0) FROM ' . Schema::ORDER_ITEM . ' i WHERE i.order_id = o.id) AS total,
                       (SELECT COUNT(*) FROM ' . Schema::ORDER_ITEM . ' i WHERE i.order_id = o.id) AS item_count
                FROM ' . Schema::ORDER . ' o LEFT JOIN ' . Schema::TABLE . ' t ON t.id = o.table_id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY o.updated_at DESC, o.id DESC';

        return array_map(function (array $r): array {
            return [
                'id'             => (int) $r['id'],
                'table_id'       => ($r['table_id'] ?? null) === null ? null : (int) $r['table_id'],
                'channel'        => (string) ($r['channel'] ?? 'dine_in'),
                'customer_name'  => ($r['customer_name'] ?? null) === null ? null : (string) $r['customer_name'],
                'table_label'    => ($r['table_label'] ?? null) === null ? null : (string) $r['table_label'],
                'status'         => (string) $r['status'],
                'paid'           => (bool) $r['paid'],
                'amount_paid'    => ($r['amount_paid'] ?? null) === null ? null : number_format((float) $r['amount_paid'], 2, '.', ''),
                'payment_method' => ($r['payment_method'] ?? null) === null ? null : (string) $r['payment_method'],
                'item_count'     => (int) $r['item_count'],
                'total'          => number_format((float) $r['total'], 2, '.', ''),
                'created_at'     => (string) $r['created_at'],
                'updated_at'     => (string) $r['updated_at'],
            ];
        }, $this->storage()->select($sql, $params));
    }

    /**
     * Orders currently in the given (allow-listed) statuses, each with its line
     * items — the kitchen queue. Oldest first (FIFO). Batched into two bound queries
     * (orders, then all their items), never N+1. An unknown status is ignored.
     *
     * @param list<string> $statuses
     * @return list<array{id:int,table_id:?int,channel:string,customer_name:?string,table_label:?string,status:string,items:list<array{name:string,qty:int}>,created_at:string,updated_at:string}>
     */
    public function ticketsByStatus(array $statuses): array
    {
        $valid = array_values(array_filter($statuses, static fn (string $s): bool => in_array($s, self::STATUSES, true)));
        if ($valid === []) {
            return [];
        }

        $placeholders = [];
        $params       = [];
        foreach ($valid as $i => $status) {
            $placeholders[]   = ':s' . $i;
            $params['s' . $i] = $status;
        }
        $orders = $this->storage()->select(
            'SELECT o.id, o.table_id, o.channel, o.customer_name, o.status, o.created_at, o.updated_at, t.label AS table_label
             FROM ' . Schema::ORDER . ' o LEFT JOIN ' . Schema::TABLE . ' t ON t.id = o.table_id
             WHERE o.status IN (' . implode(', ', $placeholders) . ') ORDER BY o.updated_at ASC, o.id ASC',
            $params,
        );
        if ($orders === []) {
            return [];
        }

        $ids       = array_map(static fn (array $r): int => (int) $r['id'], $orders);
        $itemPh    = [];
        $itemParam = [];
        foreach ($ids as $i => $oid) {
            $itemPh[]           = ':o' . $i;
            $itemParam['o' . $i] = $oid;
        }
        $itemRows = $this->storage()->select(
            'SELECT order_id, name, qty FROM ' . Schema::ORDER_ITEM . ' WHERE order_id IN (' . implode(', ', $itemPh) . ') ORDER BY id',
            $itemParam,
        );
        $byOrder = [];
        foreach ($itemRows as $r) {
            $byOrder[(int) $r['order_id']][] = ['name' => (string) $r['name'], 'qty' => (int) $r['qty']];
        }

        return array_map(static function (array $r) use ($byOrder): array {
            $id = (int) $r['id'];
            return [
                'id'            => $id,
                'table_id'      => ($r['table_id'] ?? null) === null ? null : (int) $r['table_id'],
                'channel'       => (string) ($r['channel'] ?? 'dine_in'),
                'customer_name' => ($r['customer_name'] ?? null) === null ? null : (string) $r['customer_name'],
                'table_label'   => ($r['table_label'] ?? null) === null ? null : (string) $r['table_label'],
                'status'        => (string) $r['status'],
                'items'         => $byOrder[$id] ?? [],
                'created_at'    => (string) $r['created_at'],
                'updated_at'    => (string) $r['updated_at'],
            ];
        }, $orders);
    }

    /**
     * Take payment on an order and turn its table. The **amount is computed
     * server-side** from the order's line items — never passed in, so a client can
     * never dictate what is charged; only the `method` (an allow-list) is chosen.
     * Marks the order paid + closed, and sets its table `dirty` for bussing — all in
     * one transaction. Returns the settled order.
     *
     * @return array{id:int,table_id:?int,channel:string,customer_name:?string,customer_phone:?string,table_label:?string,status:string,paid:bool,amount_paid:?string,payment_method:?string,paid_at:?string,items:list<array{id:int,menu_item_id:?int,name:string,unit_price:string,qty:int,line_total:string}>,total:string,created_at:string,updated_at:string}
     */
    public function pay(int $orderId, string $method, string $now): array
    {
        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            throw new \InvalidArgumentException('"method" must be one of: ' . implode(', ', self::PAYMENT_METHODS) . '.');
        }
        $order = $this->get($orderId);
        if ($order === null) {
            throw new \InvalidArgumentException("No order with id {$orderId}.");
        }
        if ($order['paid']) {
            throw new \InvalidArgumentException('That order is already paid.');
        }

        $amount = $order['total']; // computed from the lines, authoritative

        $this->storage()->transaction(function () use ($orderId, $order, $amount, $method, $now): void {
            $this->storage()->execute(
                'UPDATE ' . Schema::ORDER . ' SET paid = 1, amount_paid = :amount, payment_method = :method, paid_at = :now, status = :status, updated_at = :now2 WHERE id = :id',
                ['amount' => $amount, 'method' => $method, 'now' => $now, 'status' => 'closed', 'now2' => $now, 'id' => $orderId],
            );
            // Turn the table over: it now needs bussing before the next party. An
            // online order has no table, so there is nothing to turn.
            if ($order['table_id'] !== null) {
                $this->tables->setStatus($order['table_id'], 'dirty', $now);
            }
        });

        $settled = $this->get($orderId);
        assert($settled !== null);
        return $settled;
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
     * @return array{id:int,table_id:?int,channel:string,customer_name:?string,customer_phone:?string,table_label:?string,status:string,paid:bool,amount_paid:?string,payment_method:?string,paid_at:?string,items:list<array{id:int,menu_item_id:?int,name:string,unit_price:string,qty:int,line_total:string}>,total:string,created_at:string,updated_at:string}
     */
    private function hydrate(array $row, array $items): array
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += (float) $item['line_total'];
        }
        return [
            'id'             => (int) $row['id'],
            'table_id'       => ($row['table_id'] ?? null) === null ? null : (int) $row['table_id'],
            'channel'        => (string) ($row['channel'] ?? 'dine_in'),
            'customer_name'  => ($row['customer_name'] ?? null) === null ? null : (string) $row['customer_name'],
            'customer_phone' => ($row['customer_phone'] ?? null) === null ? null : (string) $row['customer_phone'],
            'table_label'    => ($row['table_label'] ?? null) === null ? null : (string) $row['table_label'],
            'status'         => (string) $row['status'],
            'paid'           => (bool) $row['paid'],
            'amount_paid'    => ($row['amount_paid'] ?? null) === null ? null : number_format((float) $row['amount_paid'], 2, '.', ''),
            'payment_method' => ($row['payment_method'] ?? null) === null ? null : (string) $row['payment_method'],
            'paid_at'        => ($row['paid_at'] ?? null) === null ? null : (string) $row['paid_at'],
            'items'          => $items,
            'total'          => number_format($total, 2, '.', ''),
            'created_at'     => (string) $row['created_at'],
            'updated_at'     => (string) $row['updated_at'],
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
