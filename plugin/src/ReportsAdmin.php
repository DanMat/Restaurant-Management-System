<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

/**
 * The manager dashboard — today's and this week's revenue, active orders, a 7-day
 * revenue breakdown, and the week's best sellers. Read-only (no forms, no actions),
 * gated on `danmat.restaurant:manage`. It computes the live date windows and asks
 * {@see Reports} for the figures. Author values (item names) are escaped on output.
 */
final class ReportsAdmin
{
    public function __construct(private Reports $reports)
    {
    }

    public function render(string $csrf = '', ?string $notice = null, string $nonce = '', ?string $now = null): string
    {
        $ref        = $now !== null ? strtotime($now) : time();
        $ref        = $ref === false ? time() : $ref;
        $todayStart = date('Y-m-d 00:00:00', $ref);
        $tomorrow   = date('Y-m-d 00:00:00', $ref + 86400);
        $weekStart  = date('Y-m-d 00:00:00', $ref - 6 * 86400);

        $today = $this->reports->revenueBetween($todayStart, $tomorrow);
        $week  = $this->reports->revenueBetween($weekStart, $tomorrow);
        $byDay = $this->reports->revenueByDay($weekStart, $tomorrow);
        $top   = $this->reports->topItems($weekStart, $tomorrow, 5);
        $active = $this->reports->activeOrders();

        return $this->styles($nonce)
            . '<div class="nb-page-head"><h1>Reports</h1></div>'
            . '<p class="nb-muted rz-intro">How service is going — revenue and what is selling. Figures are from settled (paid) orders.</p>'
            . '<div class="rz-cards">'
            . $this->card('Revenue today', $today['revenue'], $today['orders'] . ' paid')
            . $this->card('Last 7 days', $week['revenue'], $week['orders'] . ' paid')
            . $this->card('Active orders', (string) $active, 'open on the floor')
            . '</div>'
            . $this->byDay($byDay)
            . $this->topItems($top);
    }

    private function card(string $label, string $big, string $sub): string
    {
        return '<div class="rz-card"><div class="rz-card-label">' . self::e($label) . '</div>'
            . '<div class="rz-card-big">' . self::e($big) . '</div>'
            . '<div class="rz-card-sub">' . self::e($sub) . '</div></div>';
    }

    /** @param list<array{day:string,revenue:string,orders:int}> $byDay */
    private function byDay(array $byDay): string
    {
        if ($byDay === []) {
            return '<h2>Revenue by day</h2><p class="nb-muted">No paid orders in the last 7 days.</p>';
        }
        $rows = '';
        foreach ($byDay as $d) {
            $rows .= '<tr><td data-label="Day">' . self::e($d['day']) . '</td>'
                . '<td data-label="Orders">' . self::e((string) $d['orders']) . '</td>'
                . '<td data-label="Revenue">' . self::e($d['revenue']) . '</td></tr>';
        }
        return '<h2>Revenue by day</h2><table class="rz-table"><thead><tr><th>Day</th><th>Orders</th><th>Revenue</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /** @param list<array{name:string,qty:int,revenue:string}> $top */
    private function topItems(array $top): string
    {
        if ($top === []) {
            return '<h2>Top items (7 days)</h2><p class="nb-muted">Nothing sold yet.</p>';
        }
        $rows = '';
        foreach ($top as $t) {
            $rows .= '<tr><td data-label="Item">' . self::e($t['name']) . '</td>'
                . '<td data-label="Sold">' . self::e((string) $t['qty']) . '</td>'
                . '<td data-label="Revenue">' . self::e($t['revenue']) . '</td></tr>';
        }
        return '<h2>Top items (7 days)</h2><table class="rz-table"><thead><tr><th>Item</th><th>Sold</th><th>Revenue</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    private function styles(string $nonce): string
    {
        return '<style nonce="' . self::e($nonce) . '">'
            . '.rz-intro{max-width:60ch}'
            . '.rz-cards{display:flex;gap:1rem;flex-wrap:wrap;margin:0 0 1.5rem}'
            . '.rz-card{flex:1 1 12rem;border:1px solid rgba(128,128,128,.2);border-radius:10px;padding:.9rem 1rem}'
            . '.rz-card-label{font-size:.8rem;font-weight:700;opacity:.7;text-transform:uppercase;letter-spacing:.03em}'
            . '.rz-card-big{font-size:1.8rem;font-weight:800;font-variant-numeric:tabular-nums;margin:.2rem 0}'
            . '.rz-card-sub{font-size:.8rem;opacity:.7}'
            . '.rz-table{width:100%;border-collapse:collapse;margin:.5rem 0 1.5rem;max-width:36rem}'
            . '.rz-table th,.rz-table td{text-align:left;padding:.5rem .5rem;border-bottom:1px solid rgba(128,128,128,.2)}'
            . '.rz-table td:last-child,.rz-table th:last-child{text-align:right;font-variant-numeric:tabular-nums}'
            . '@media (max-width:36rem){'
            . '.rz-table,.rz-table tbody,.rz-table tr,.rz-table td{display:block}'
            . '.rz-table thead{display:none}'
            . '.rz-table tr{border:1px solid rgba(128,128,128,.25);border-radius:8px;margin:0 0 .5rem;padding:.3rem .6rem}'
            . '.rz-table td{border:0;padding:.25rem 0;display:flex;justify-content:space-between}'
            . '.rz-table td:last-child{text-align:right}'
            . '.rz-table td[data-label]:before{content:attr(data-label);font-weight:700;font-size:.8rem;opacity:.7}'
            . '}'
            . '</style>';
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
