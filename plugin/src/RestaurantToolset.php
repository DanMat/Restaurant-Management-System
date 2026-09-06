<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

use Nimbus\Api\EntryOpContext;
use Nimbus\Api\TokenPrincipal;
use Nimbus\Mcp\PluginTool;
use Nimbus\Mcp\PluginToolset;

/**
 * The restaurant over MCP — an agent is a first-class operator of the floor
 * (ADR 0009/0016). Slice 1 exposes the tables:
 *
 * - `tables` (list/filter) and `table_get` — reads;
 * - `table_set` (create/update), `table_status` (the floor quick action) and
 *   `table_delete` — writes.
 *
 * The {@see PluginToolset} base gates every tool on this plugin's own
 * `danmat.restaurant` capability (ADR 0015/0016): a read needs `:read`, a write
 * needs `:write`, both unreachable by a content `*:write` token, and a denied tool
 * reports as unknown. Input/validation errors come back as **data**, not a 500, so
 * an agent can correct a bad value rather than crash.
 */
final class RestaurantToolset extends PluginToolset
{
    public function __construct(
        private Tables $tables,
        private Orders $orders,
        private MenuSource $menu,
        private Reservations $reservations,
        private Reports $reports,
    ) {
    }

    public function namespace(): string
    {
        return 'restaurant';
    }

    protected function tools(): array
    {
        $id = ['type' => 'integer', 'description' => 'The table id.'];

        return [
            new PluginTool('tables', 'read', 'List tables on the floor, optionally filtered by status.', [
                'type'       => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => Tables::STATUSES, 'description' => 'Optional filter: open / occupied / dirty / reserved.'],
                ],
            ], $this->tables(...)),

            new PluginTool('table_get', 'read', 'One table by id, or none.', [
                'type'       => 'object',
                'required'   => ['id'],
                'properties' => ['id' => $id],
            ], $this->tableGet(...)),

            new PluginTool('table_set', 'write', 'Create a table (omit id) or update one (with id). Only the fields you send change.', [
                'type'       => 'object',
                'properties' => [
                    'id'     => ['type' => 'integer', 'description' => 'Existing table id to update; omit to create.'],
                    'label'  => ['type' => 'string', 'description' => 'The table label/number (required to create; unique).'],
                    'seats'  => ['type' => 'integer', 'description' => 'How many it seats. Defaults to 2.'],
                    'status' => ['type' => 'string', 'enum' => Tables::STATUSES, 'description' => 'Table status. Defaults to open.'],
                ],
            ], $this->tableSet(...)),

            new PluginTool('table_status', 'write', 'Move a table to a status (seat = occupied, clear = dirty, clean = open, reserve = reserved).', [
                'type'       => 'object',
                'required'   => ['id', 'status'],
                'properties' => [
                    'id'     => $id,
                    'status' => ['type' => 'string', 'enum' => Tables::STATUSES, 'description' => 'The new status.'],
                ],
            ], $this->tableStatus(...)),

            new PluginTool('table_delete', 'write', 'Delete a table outright by id.', [
                'type'       => 'object',
                'required'   => ['id'],
                'properties' => ['id' => $id],
            ], $this->tableDelete(...)),

            new PluginTool('menu', 'read', 'List the menu items available to order (from the menu collection), each with a price.', [
                'type'       => 'object',
                'properties' => new \stdClass(),
            ], $this->menu(...)),

            new PluginTool('order_open', 'write', 'Open a new order on a table (which becomes occupied). Returns the order.', [
                'type'       => 'object',
                'required'   => ['table_id'],
                'properties' => ['table_id' => ['type' => 'integer', 'description' => 'The table to open the order on.']],
            ], $this->orderOpen(...)),

            new PluginTool('orders', 'read', 'List orders, optionally filtered by status and/or table.', [
                'type'       => 'object',
                'properties' => [
                    'status'   => ['type' => 'string', 'enum' => Orders::STATUSES, 'description' => 'Optional workflow-status filter.'],
                    'table_id' => ['type' => 'integer', 'description' => 'Optional table filter.'],
                ],
            ], $this->orders(...)),

            new PluginTool('order_get', 'read', 'One order with its line items and computed total, or none.', [
                'type'       => 'object',
                'required'   => ['id'],
                'properties' => ['id' => ['type' => 'integer', 'description' => 'The order id.']],
            ], $this->orderGet(...)),

            new PluginTool('order_status', 'write', 'Advance an order through the workflow (open→sent→preparing→ready→served→closed).', [
                'type'       => 'object',
                'required'   => ['id', 'status'],
                'properties' => [
                    'id'     => ['type' => 'integer', 'description' => 'The order id.'],
                    'status' => ['type' => 'string', 'enum' => Orders::STATUSES, 'description' => 'The new status.'],
                ],
            ], $this->orderStatus(...)),

            new PluginTool('order_add_item', 'write', 'Add a line to an order — from the menu (menu_item_id, snapshotting its name+price) or a manual line (name+price).', [
                'type'       => 'object',
                'required'   => ['order_id'],
                'properties' => [
                    'order_id'     => ['type' => 'integer', 'description' => 'The order to add to.'],
                    'menu_item_id' => ['type' => 'integer', 'description' => 'A menu item id to add (snapshots its name + price).'],
                    'name'         => ['type' => 'string', 'description' => 'A manual line name (when not adding from the menu).'],
                    'price'        => ['type' => 'string', 'description' => 'A manual line unit price (with menu_item_id omitted).'],
                    'qty'          => ['type' => 'integer', 'description' => 'How many. Defaults to 1.'],
                ],
            ], $this->orderAddItem(...)),

            new PluginTool('order_set_item_qty', 'write', 'Change a line item quantity; 0 removes it.', [
                'type'       => 'object',
                'required'   => ['item_id', 'qty'],
                'properties' => [
                    'item_id' => ['type' => 'integer', 'description' => 'The line item id.'],
                    'qty'     => ['type' => 'integer', 'description' => 'The new quantity (0 to remove).'],
                ],
            ], $this->orderSetItemQty(...)),

            new PluginTool('order_remove_item', 'write', 'Remove a line item from an order.', [
                'type'       => 'object',
                'required'   => ['item_id'],
                'properties' => ['item_id' => ['type' => 'integer', 'description' => 'The line item id.']],
            ], $this->orderRemoveItem(...)),

            new PluginTool('order_pay', 'write', 'Take payment on an order and turn its table. The amount is the computed order total (never passed in); choose only the method. Closes the order.', [
                'type'       => 'object',
                'required'   => ['id', 'method'],
                'properties' => [
                    'id'     => ['type' => 'integer', 'description' => 'The order id.'],
                    'method' => ['type' => 'string', 'enum' => Orders::PAYMENT_METHODS, 'description' => 'How it was paid: cash / card / other.'],
                ],
            ], $this->orderPay(...)),

            new PluginTool('order_delete', 'write', 'Delete an order and its line items.', [
                'type'       => 'object',
                'required'   => ['id'],
                'properties' => ['id' => ['type' => 'integer', 'description' => 'The order id.']],
            ], $this->orderDelete(...)),

            new PluginTool('kitchen', 'read', 'The kitchen queue: tickets in the kitchen (sent/preparing/ready), oldest first, each with its items. Advance one with order_status.', [
                'type'       => 'object',
                'properties' => new \stdClass(),
            ], $this->kitchen(...)),

            new PluginTool('reservations', 'read', 'List reservations, soonest first, optionally filtered by status.', [
                'type'       => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => Reservations::STATUSES, 'description' => 'Optional: booked / seated / cancelled / no_show.'],
                ],
            ], $this->reservations(...)),

            new PluginTool('reservation_get', 'read', 'One reservation by id, or none. (contact_id links to a CRM guest; open it in the CRM, which is separately gated.)', [
                'type'       => 'object',
                'required'   => ['id'],
                'properties' => ['id' => ['type' => 'integer', 'description' => 'The reservation id.']],
            ], $this->reservationGet(...)),

            new PluginTool('reservation_set', 'write', 'Create a reservation (omit id) or update one (with id). Only the fields you send change.', [
                'type'       => 'object',
                'properties' => [
                    'id'          => ['type' => 'integer', 'description' => 'Existing reservation id to update; omit to create.'],
                    'party_name'  => ['type' => 'string', 'description' => 'The booking name (required to create).'],
                    'party_size'  => ['type' => 'integer', 'description' => 'How many. Defaults to 2.'],
                    'reserved_at' => ['type' => 'string', 'description' => 'When, "YYYY-MM-DD HH:MM[:SS]". Defaults to now.'],
                    'table_id'    => ['type' => 'integer', 'description' => 'An existing table to hold. Optional; blank to unassign.'],
                    'contact_id'  => ['type' => 'integer', 'description' => 'The guest\'s CRM contact id to link. Optional; not resolved here — the restaurant never reads CRM data.'],
                    'status'      => ['type' => 'string', 'enum' => Reservations::STATUSES, 'description' => 'Reservation status. Defaults to booked.'],
                    'notes'       => ['type' => 'string', 'description' => 'Free-text notes. Optional.'],
                ],
            ], $this->reservationSet(...)),

            new PluginTool('reservation_status', 'write', 'Set a reservation status (booked/seated/cancelled/no_show).', [
                'type'       => 'object',
                'required'   => ['id', 'status'],
                'properties' => [
                    'id'     => ['type' => 'integer', 'description' => 'The reservation id.'],
                    'status' => ['type' => 'string', 'enum' => Reservations::STATUSES, 'description' => 'The new status.'],
                ],
            ], $this->reservationStatus(...)),

            new PluginTool('reservation_delete', 'write', 'Delete a reservation by id.', [
                'type'       => 'object',
                'required'   => ['id'],
                'properties' => ['id' => ['type' => 'integer', 'description' => 'The reservation id.']],
            ], $this->reservationDelete(...)),

            new PluginTool('reports', 'read', 'A revenue summary: today and the last 7 days (from paid orders), active orders, and the week\'s best-selling items.', [
                'type'       => 'object',
                'properties' => new \stdClass(),
            ], $this->reports(...)),
        ];
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function reports(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        $ref        = time();
        $todayStart = date('Y-m-d 00:00:00', $ref);
        $tomorrow   = date('Y-m-d 00:00:00', $ref + 86400);
        $weekStart  = date('Y-m-d 00:00:00', $ref - 6 * 86400);

        return [
            'today'         => $this->reports->revenueBetween($todayStart, $tomorrow),
            'last_7_days'   => $this->reports->revenueBetween($weekStart, $tomorrow),
            'active_orders' => $this->reports->activeOrders(),
            'top_items'     => $this->reports->topItems($weekStart, $tomorrow, 5),
        ];
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function reservations(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        $list = $this->reservations->all($this->nullableStr($a, 'status'));
        return ['reservations' => $list, 'count' => count($list)];
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function reservationGet(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        $id = $this->requireInt($a, 'id');
        return ['id' => $id, 'reservation' => $this->reservations->get($id)];
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function reservationSet(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        return $this->guard(function () use ($a): array {
            $id = $this->reservations->save($this->nullableInt($a, 'id'), $a, $this->now());
            return ['ok' => true, 'reservation' => $this->reservations->get($id)];
        });
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function reservationStatus(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        return $this->guard(function () use ($a): array {
            $id      = $this->requireInt($a, 'id');
            $changed = $this->reservations->setStatus($id, (string) ($a['status'] ?? ''), $this->now());
            return ['ok' => true, 'changed' => $changed > 0, 'reservation' => $this->reservations->get($id)];
        });
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function reservationDelete(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        $id = $this->requireInt($a, 'id');
        return ['ok' => true, 'deleted' => $this->reservations->delete($id) > 0];
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function kitchen(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        $tickets = $this->orders->ticketsByStatus(['sent', 'preparing', 'ready']);
        return ['tickets' => $tickets, 'count' => count($tickets)];
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function menu(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        $items = $this->menu->items();
        return ['menu' => $items, 'count' => count($items)];
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function orderOpen(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        return $this->guard(function () use ($a): array {
            $orderId = $this->orders->open($this->requireInt($a, 'table_id'), $this->now());
            return ['ok' => true, 'order' => $this->orders->get($orderId)];
        });
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function orders(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        $list = $this->orders->all($this->nullableStr($a, 'status'), $this->nullableInt($a, 'table_id'));
        return ['orders' => $list, 'count' => count($list)];
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function orderGet(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        $id = $this->requireInt($a, 'id');
        return ['id' => $id, 'order' => $this->orders->get($id)];
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function orderStatus(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        return $this->guard(function () use ($a): array {
            $id      = $this->requireInt($a, 'id');
            $changed = $this->orders->setStatus($id, (string) ($a['status'] ?? ''), $this->now());
            return ['ok' => true, 'changed' => $changed > 0, 'order' => $this->orders->get($id)];
        });
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function orderAddItem(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        return $this->guard(function () use ($a): array {
            $orderId = $this->requireInt($a, 'order_id');
            $qtyRaw  = $this->nullableInt($a, 'qty');
            $this->orders->addItem(
                $orderId,
                $this->nullableInt($a, 'menu_item_id'),
                $this->nullableStr($a, 'name'),
                $this->nullableStr($a, 'price'),
                $qtyRaw ?? 1,
                $this->now(),
            );
            return ['ok' => true, 'order' => $this->orders->get($orderId)];
        });
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function orderSetItemQty(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        return $this->guard(function () use ($a): array {
            $itemId  = $this->requireInt($a, 'item_id');
            $qty     = $this->requireInt($a, 'qty');
            $changed = $this->orders->setItemQty($itemId, $qty, $this->now());
            return ['ok' => true, 'changed' => $changed > 0];
        });
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function orderRemoveItem(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        $itemId = $this->requireInt($a, 'item_id');
        return ['ok' => true, 'removed' => $this->orders->removeItem($itemId) > 0];
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function orderDelete(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        $id = $this->requireInt($a, 'id');
        return ['ok' => true, 'deleted' => $this->orders->delete($id) > 0];
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function orderPay(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        return $this->guard(function () use ($a): array {
            $order = $this->orders->pay($this->requireInt($a, 'id'), (string) ($a['method'] ?? ''), $this->now());
            return ['ok' => true, 'order' => $order];
        });
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function tables(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        $list = $this->tables->all($this->nullableStr($a, 'status'));
        return ['tables' => $list, 'count' => count($list)];
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function tableGet(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        $id = $this->requireInt($a, 'id');
        return ['id' => $id, 'table' => $this->tables->get($id)];
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function tableSet(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        return $this->guard(function () use ($a): array {
            $id = $this->tables->save($this->nullableInt($a, 'id'), $a, $this->now());
            return ['ok' => true, 'table' => $this->tables->get($id)];
        });
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function tableStatus(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        return $this->guard(function () use ($a): array {
            $id      = $this->requireInt($a, 'id');
            $status  = (string) ($a['status'] ?? '');
            $changed = $this->tables->setStatus($id, $status, $this->now());
            return ['ok' => true, 'changed' => $changed > 0, 'table' => $this->tables->get($id)];
        });
    }

    /**
     * @param array<string,mixed> $a
     * @return array<string,mixed>
     */
    private function tableDelete(array $a, TokenPrincipal $p, EntryOpContext $c): array
    {
        $id = $this->requireInt($a, 'id');
        return ['ok' => true, 'deleted' => $this->tables->delete($id) > 0];
    }

    // --- helpers ---------------------------------------------------------

    /**
     * @param \Closure():array<string,mixed> $work
     * @return array<string,mixed>
     */
    private function guard(\Closure $work): array
    {
        try {
            return $work();
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'error' => 'invalid', 'message' => $e->getMessage()];
        }
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** @param array<string,mixed> $a */
    private function requireInt(array $a, string $key): int
    {
        $v = $this->nullableInt($a, $key);
        if ($v === null) {
            throw new \InvalidArgumentException("\"{$key}\" is required.");
        }
        return $v;
    }

    /** @param array<string,mixed> $a */
    private function nullableInt(array $a, string $key): ?int
    {
        $v = $a[$key] ?? null;
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v)) {
            return $v;
        }
        if (is_string($v) && preg_match('/^\d+$/', trim($v)) === 1) {
            return (int) trim($v);
        }
        throw new \InvalidArgumentException("\"{$key}\" must be a whole number.");
    }

    /** @param array<string,mixed> $a */
    private function nullableStr(array $a, string $key): ?string
    {
        $v = $a[$key] ?? null;
        if (!is_string($v) && !is_int($v) && !is_float($v)) {
            return null;
        }
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }
}
