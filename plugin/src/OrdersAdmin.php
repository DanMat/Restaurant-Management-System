<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

/**
 * Orders — a list of orders and a single-order screen (line items, a menu picker,
 * quantities, the workflow, and the computed total). An order is opened on a table
 * (which occupies it). Same admin discipline as the floor: every author value
 * escaped ({@see e}), one nonce'd `<style>` block, gated on `danmat.restaurant:write`
 * + CSRF, mobile-first. The total shown here is computed server-side from the lines —
 * never a client value.
 */
final class OrdersAdmin
{
    private const NOTICES = [
        'opened'    => ['ok', 'Order opened.'],
        'added'     => ['ok', 'Item added.'],
        'updated'   => ['ok', 'Order updated.'],
        'removed'   => ['ok', 'Item removed.'],
        'deleted'   => ['ok', 'Order deleted.'],
        'paid'      => ['ok', 'Payment taken — table sent for bussing.'],
        'notable'   => ['err', 'Pick a table to open an order on.'],
        'invalid'   => ['err', 'Check the details and try again.'],
    ];

    private const STATUS_LABELS = [
        'open'      => 'Open',
        'sent'      => 'Sent to kitchen',
        'preparing' => 'Preparing',
        'ready'     => 'Ready',
        'served'    => 'Served',
        'closed'    => 'Closed',
    ];

    public function __construct(
        private Orders $orders,
        private Tables $tables,
        private MenuSource $menu,
    ) {
    }

    public function render(string $csrf = '', ?string $notice = null, ?string $view = null, ?string $status = null, string $nonce = ''): string
    {
        $viewId    = ($view !== null && preg_match('/^\d+$/', trim($view)) === 1) ? (int) trim($view) : null;
        $viewOrder = $viewId !== null ? $this->orders->get($viewId) : null;

        $html = $this->styles($nonce) . Branding::head('Orders', 'Open a table, build the order, settle up.', $nonce) . $this->notice($notice);

        if ($viewOrder !== null) {
            return $html . $this->orderScreen($csrf, $viewOrder);
        }

        $filter = ($status !== null && in_array(trim($status), Orders::STATUSES, true)) ? trim($status) : null;
        return $html
            . $this->openForm($csrf)
            . $this->filterBar($filter)
            . $this->list($this->orders->all($filter));
    }

    private function openForm(string $csrf): string
    {
        $options = '<option value="">— pick a table —</option>';
        foreach ($this->tables->all() as $t) {
            $options .= '<option value="' . self::e((string) $t['id']) . '">' . self::e((string) $t['label']) . ' (' . self::e((string) $t['status']) . ')</option>';
        }
        return '<h2>Open an order</h2>'
            . '<form method="post" action="/admin/restaurant-orders/order-open" class="rz-form rz-inline">'
            . '<input type="hidden" name="_csrf" value="' . self::e($csrf) . '">'
            . '<label>Table<select name="table_id">' . $options . '</select></label>'
            . '<div class="rz-actions"><button type="submit" class="nb-btn">Open order</button></div>'
            . '</form>';
    }

    private function filterBar(?string $active): string
    {
        $chips = '<a class="rz-chip' . ($active === null ? ' is-active' : '') . '" href="/admin/restaurant-orders">All</a>';
        foreach (Orders::STATUSES as $s) {
            $chips .= '<a class="rz-chip' . ($active === $s ? ' is-active' : '') . '" href="/admin/restaurant-orders?status=' . self::e($s) . '">' . self::e(self::STATUS_LABELS[$s]) . '</a>';
        }
        return '<div class="rz-filter" role="group" aria-label="Filter by status">' . $chips . '</div>';
    }

    /** @param list<array<string,mixed>> $orders */
    private function list(array $orders): string
    {
        if ($orders === []) {
            return '<p class="nb-muted">No orders.</p>';
        }
        $rows = '';
        foreach ($orders as $o) {
            $rows .= '<tr>'
                . '<td data-label="Order"><a href="/admin/restaurant-orders?view=' . self::e((string) $o['id']) . '">#' . self::e((string) $o['id']) . '</a></td>'
                . '<td data-label="Table">' . self::e((string) ($o['table_label'] ?? '—')) . '</td>'
                . '<td data-label="Status"><span class="rz-badge rz-badge-' . self::e((string) $o['status']) . '">' . self::e(self::STATUS_LABELS[(string) $o['status']] ?? (string) $o['status']) . '</span></td>'
                . '<td data-label="Items">' . self::e((string) $o['item_count']) . '</td>'
                . '<td data-label="Total">' . self::e((string) $o['total']) . '</td>'
                . '</tr>';
        }
        return '<table class="rz-table"><thead><tr><th>Order</th><th>Table</th><th>Status</th><th>Items</th><th>Total</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /** @param array<string,mixed> $order */
    private function orderScreen(string $csrf, array $order): string
    {
        $id = (int) $order['id'];

        $lines = '';
        foreach ($order['items'] as $item) {
            $lines .= '<tr>'
                . '<td data-label="Item">' . self::e((string) $item['name']) . '</td>'
                . '<td data-label="Price">' . self::e((string) $item['unit_price']) . '</td>'
                . '<td data-label="Qty"><form method="post" action="/admin/restaurant-orders/order-set-qty" class="rz-qty">'
                . '<input type="hidden" name="_csrf" value="' . self::e($csrf) . '">'
                . '<input type="hidden" name="view" value="' . self::e((string) $id) . '">'
                . '<input type="hidden" name="item_id" value="' . self::e((string) $item['id']) . '">'
                . '<input type="number" name="qty" value="' . self::e((string) $item['qty']) . '" min="0" max="999" aria-label="Quantity">'
                . '<button type="submit" class="nb-btn nb-btn-quiet">Set</button></form></td>'
                . '<td data-label="Line">' . self::e((string) $item['line_total']) . '</td>'
                . '<td data-label="" class="rz-rowact"><form method="post" action="/admin/restaurant-orders/order-remove-item" data-confirm="Remove this item?">'
                . '<input type="hidden" name="_csrf" value="' . self::e($csrf) . '">'
                . '<input type="hidden" name="view" value="' . self::e((string) $id) . '">'
                . '<input type="hidden" name="item_id" value="' . self::e((string) $item['id']) . '">'
                . '<button type="submit" class="rz-link-danger">Remove</button></form></td>'
                . '</tr>';
        }
        if ($lines === '') {
            $lines = '<tr><td colspan="5" class="nb-muted">No items yet — add from the menu below.</td></tr>';
        }

        return '<p><a href="/admin/restaurant-orders">← All orders</a></p>'
            . '<div class="rz-order-head">'
            . '<h2>Order #' . self::e((string) $id) . ' · ' . self::e((string) ($order['table_label'] ?? '—')) . '</h2>'
            . '<span class="rz-badge rz-badge-' . self::e((string) $order['status']) . '">' . self::e(self::STATUS_LABELS[(string) $order['status']] ?? (string) $order['status']) . '</span>'
            . '</div>'
            . '<table class="rz-table"><thead><tr><th>Item</th><th>Price</th><th>Qty</th><th>Line</th><th></th></tr></thead><tbody>' . $lines . '</tbody>'
            . '<tfoot><tr><th colspan="3" style="text-align:right">Total</th><th>' . self::e((string) $order['total']) . '</th><th></th></tr></tfoot></table>'
            . $this->addItemForm($csrf, $id)
            . $this->statusActions($csrf, $id, (string) $order['status'])
            . $this->paymentBlock($csrf, $order)
            . '<form method="post" action="/admin/restaurant-orders/order-delete" data-confirm="Delete this whole order?" class="rz-order-del">'
            . '<input type="hidden" name="_csrf" value="' . self::e($csrf) . '">'
            . '<input type="hidden" name="id" value="' . self::e((string) $id) . '">'
            . '<button type="submit" class="rz-link-danger">Delete order</button></form>';
    }

    private function addItemForm(string $csrf, int $orderId): string
    {
        $options = '<option value="">— pick a menu item —</option>';
        foreach ($this->menu->items() as $m) {
            $label = $m['name'] . ' · ' . $m['price'] . ($m['category'] !== null ? ' · ' . $m['category'] : '');
            $options .= '<option value="' . self::e((string) $m['id']) . '">' . self::e($label) . '</option>';
        }
        return '<h3>Add an item</h3>'
            . '<form method="post" action="/admin/restaurant-orders/order-add-item" class="rz-form rz-inline">'
            . '<input type="hidden" name="_csrf" value="' . self::e($csrf) . '">'
            . '<input type="hidden" name="view" value="' . self::e((string) $orderId) . '">'
            . '<input type="hidden" name="order_id" value="' . self::e((string) $orderId) . '">'
            . '<label>Menu item<select name="menu_item_id">' . $options . '</select></label>'
            . '<label>Qty<input type="number" name="qty" value="1" min="1" max="999"></label>'
            . '<div class="rz-actions"><button type="submit" class="nb-btn">Add</button></div>'
            . '</form>';
    }

    private function statusActions(string $csrf, int $orderId, string $status): string
    {
        // "served" is not offered a Close button — taking payment closes the order.
        $moves = match ($status) {
            'open'                        => [['sent', 'Send to kitchen']],
            'sent', 'preparing', 'ready'  => [['served', 'Mark served']],
            default                       => [],
        };
        if ($moves === []) {
            return '';
        }
        $html = '<div class="rz-status-acts">';
        foreach ($moves as [$to, $verb]) {
            $html .= '<form method="post" action="/admin/restaurant-orders/order-status" class="rz-act">'
                . '<input type="hidden" name="_csrf" value="' . self::e($csrf) . '">'
                . '<input type="hidden" name="view" value="' . self::e((string) $orderId) . '">'
                . '<input type="hidden" name="id" value="' . self::e((string) $orderId) . '">'
                . '<input type="hidden" name="status" value="' . self::e($to) . '">'
                . '<button type="submit" class="nb-btn">' . self::e($verb) . '</button></form>';
        }
        return $html . '</div>';
    }

    /** @param array<string,mixed> $order */
    private function paymentBlock(string $csrf, array $order): string
    {
        if ((bool) $order['paid']) {
            $method = self::e((string) ($order['payment_method'] ?? ''));
            $amount = self::e((string) ($order['amount_paid'] ?? $order['total']));
            $when   = self::e((string) ($order['paid_at'] ?? ''));
            return '<div class="rz-paid">✓ Paid ' . $amount . ($method !== '' ? ' by ' . ucfirst($method) : '') . ($when !== '' ? ' · ' . $when : '') . '</div>';
        }

        $options = '';
        foreach (Orders::PAYMENT_METHODS as $m) {
            $options .= '<option value="' . self::e($m) . '">' . self::e(ucfirst($m)) . '</option>';
        }
        // The amount is not an input — it is the computed total, charged server-side.
        return '<div class="rz-pay"><h3>Take payment</h3>'
            . '<p class="rz-pay-total">Total due <strong>' . self::e((string) $order['total']) . '</strong></p>'
            . '<form method="post" action="/admin/restaurant-orders/order-pay" class="rz-form rz-inline">'
            . '<input type="hidden" name="_csrf" value="' . self::e($csrf) . '">'
            . '<input type="hidden" name="view" value="' . self::e((string) $order['id']) . '">'
            . '<input type="hidden" name="id" value="' . self::e((string) $order['id']) . '">'
            . '<label>Method<select name="method">' . $options . '</select></label>'
            . '<div class="rz-actions"><button type="submit" class="nb-btn">Take payment</button></div>'
            . '</form></div>';
    }

    private function notice(?string $code): string
    {
        if ($code === null || !isset(self::NOTICES[$code])) {
            return '';
        }
        [$kind, $message] = self::NOTICES[$code];
        return '<div class="nb-notice nb-notice-' . ($kind === 'ok' ? 'ok' : 'err') . '">' . self::e($message) . '</div>';
    }

    private function styles(string $nonce): string
    {
        return '<style nonce="' . self::e($nonce) . '">'
            . '.rz-form{max-width:44rem;display:flex;flex-direction:column;gap:.75rem;margin:0 0 1.5rem}'
            . '.rz-inline{flex-flow:row wrap;align-items:flex-end}'
            . '.rz-form label{display:flex;flex-direction:column;gap:.25rem;flex:1 1 12rem;min-width:0;font-weight:600;font-size:.85rem}'
            . '.rz-form input,.rz-form select{font:inherit;padding:.5rem .6rem;min-height:44px;box-sizing:border-box;width:100%;max-width:100%}'
            . '.rz-filter{display:flex;flex-wrap:wrap;gap:.4rem;margin:0 0 1rem}'
            . '.rz-chip{text-decoration:none;background:rgba(128,128,128,.12);border-radius:999px;padding:.3rem .8rem;font-size:.82rem;color:inherit;min-height:32px;display:inline-flex;align-items:center}'
            . '.rz-chip.is-active{background:rgba(52,152,219,.22);color:#2471a3;font-weight:700}'
            . '.rz-table{width:100%;border-collapse:collapse;margin:0 0 1.25rem}'
            . '.rz-table th,.rz-table td{text-align:left;padding:.55rem .5rem;border-bottom:1px solid rgba(128,128,128,.2)}'
            . '.rz-table tfoot th{border-bottom:0;font-variant-numeric:tabular-nums}'
            . '.rz-qty{display:flex;gap:.35rem;align-items:center}'
            . '.rz-qty input{width:4.5rem;font:inherit;padding:.35rem .4rem;min-height:40px}'
            . '.rz-order-head{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}'
            . '.rz-badge{font-size:.68rem;font-weight:700;padding:.12rem .5rem;border-radius:999px;text-transform:uppercase;letter-spacing:.03em;background:rgba(128,128,128,.16)}'
            . '.rz-badge-open{background:rgba(39,174,96,.18);color:#1e8449}'
            . '.rz-badge-sent,.rz-badge-preparing{background:rgba(184,134,11,.18);color:#9a7d0a}'
            . '.rz-badge-ready{background:rgba(41,128,185,.18);color:#2471a3}'
            . '.rz-badge-served{background:rgba(128,128,128,.2)}'
            . '.rz-badge-closed{background:rgba(120,120,120,.15);opacity:.8}'
            . '.rz-status-acts{display:flex;gap:.5rem;flex-wrap:wrap;margin:0 0 1.25rem}'
            . '.rz-pay{border-top:1px solid rgba(128,128,128,.2);padding-top:1rem;margin-top:.5rem}'
            . '.rz-pay-total{font-size:1.05rem}'
            . '.rz-paid{margin:.5rem 0 0;padding:.6rem .8rem;border-radius:8px;background:rgba(39,174,96,.15);color:#1e8449;font-weight:700}'
            . '.rz-rowact{text-align:right}'
            . '.rz-order-del{margin-top:1rem}'
            . '.rz-link-danger{background:none;border:0;color:#c0392b;font:inherit;cursor:pointer;text-decoration:underline;padding:.25rem 0;min-height:36px}'
            . '@media (max-width:40rem){'
            . '.rz-table,.rz-table tbody,.rz-table tr,.rz-table td{display:block}'
            . '.rz-table thead,.rz-table tfoot{display:none}'
            . '.rz-table tr{border:1px solid rgba(128,128,128,.25);border-radius:8px;margin:0 0 .6rem;padding:.35rem .6rem}'
            . '.rz-table td{border:0;padding:.3rem 0;display:flex;justify-content:space-between;gap:1rem}'
            . '.rz-table td[data-label]:before{content:attr(data-label);font-weight:700;font-size:.8rem;opacity:.7}'
            . '}'
            . '</style>';
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
