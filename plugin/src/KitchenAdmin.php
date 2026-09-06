<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

/**
 * The kitchen display — a cook's screen of the tickets in the kitchen, in three
 * columns by state: **New** (sent), **Preparing**, **Ready** (plated, waiting for
 * the floor to serve). The cook advances a ticket New → Preparing → Ready; the
 * floor owns "served" from the order screen. A capability-gated admin page (not a
 * public route, per the security review), CSRF on every advance, mobile-first, and
 * a nonce'd auto-refresh so a bump on one station shows on the pass.
 */
final class KitchenAdmin
{
    /** The kitchen's columns, and the forward move each offers (null = terminal here). */
    private const COLUMNS = [
        'sent'      => ['label' => 'New', 'next' => 'preparing', 'verb' => 'Start'],
        'preparing' => ['label' => 'Preparing', 'next' => 'ready', 'verb' => 'Ready'],
        'ready'     => ['label' => 'Ready', 'next' => null, 'verb' => null],
    ];

    private const NOTICES = [
        'advanced' => ['ok', 'Ticket updated.'],
        'invalid'  => ['err', 'Could not update that ticket.'],
    ];

    public function __construct(private Orders $orders)
    {
    }

    public function render(string $csrf = '', ?string $notice = null, string $nonce = ''): string
    {
        $tickets = $this->orders->ticketsByStatus(array_keys(self::COLUMNS));

        $byStatus = [];
        foreach (array_keys(self::COLUMNS) as $s) {
            $byStatus[$s] = [];
        }
        foreach ($tickets as $t) {
            $byStatus[(string) $t['status']][] = $t;
        }

        $cols = '';
        foreach (self::COLUMNS as $status => $col) {
            $cols .= $this->column($csrf, $status, $col, $byStatus[$status]);
        }

        return $this->styles($nonce)
            . Branding::head('Kitchen', 'Tickets on the line, oldest first.', $nonce)
            . $this->notice($notice)
            . '<p class="nb-muted kx-intro">Tickets on the line. Start a ticket when you begin it, mark it ready when it is up for the pass.</p>'
            . '<div class="kx-board">' . $cols . '</div>'
            . $this->autoRefresh($nonce);
    }

    /**
     * @param array{label:string,next:?string,verb:?string} $col
     * @param list<array<string,mixed>>                      $tickets
     */
    private function column(string $csrf, string $status, array $col, array $tickets): string
    {
        $cards = '';
        foreach ($tickets as $t) {
            $cards .= $this->ticket($csrf, $t, $col);
        }
        if ($cards === '') {
            $cards = '<p class="nb-muted kx-empty">Nothing here.</p>';
        }

        return '<section class="kx-col kx-col-' . self::e($status) . '">'
            . '<h2>' . self::e($col['label']) . ' <span class="kx-count">' . count($tickets) . '</span></h2>'
            . '<div class="kx-col-body">' . $cards . '</div></section>';
    }

    /**
     * @param array<string,mixed>                            $t
     * @param array{label:string,next:?string,verb:?string}  $col
     */
    private function ticket(string $csrf, array $t, array $col): string
    {
        $lines = '';
        foreach ($t['items'] as $item) {
            $lines .= '<li><span class="kx-qty">' . self::e((string) $item['qty']) . '×</span> ' . self::e((string) $item['name']) . '</li>';
        }
        if ($lines === '') {
            $lines = '<li class="nb-muted">No items</li>';
        }

        $advance = '';
        if ($col['next'] !== null && $col['verb'] !== null) {
            $advance = '<form method="post" action="/admin/restaurant-kitchen/advance" class="kx-advance">'
                . '<input type="hidden" name="_csrf" value="' . self::e($csrf) . '">'
                . '<input type="hidden" name="id" value="' . self::e((string) $t['id']) . '">'
                . '<input type="hidden" name="status" value="' . self::e($col['next']) . '">'
                . '<button type="submit" class="nb-btn">' . self::e($col['verb']) . '</button></form>';
        }

        return '<article class="kx-ticket">'
            . '<div class="kx-ticket-top"><span class="kx-table">' . self::e((string) ($t['table_label'] ?? '—')) . '</span>'
            . '<span class="kx-age">#' . self::e((string) $t['id']) . ' · ' . self::e($this->age((string) $t['updated_at'])) . '</span></div>'
            . '<ul class="kx-items">' . $lines . '</ul>'
            . $advance
            . '</article>';
    }

    /** A compact "how long on the line" label from a datetime. */
    private function age(string $updatedAt): string
    {
        $ts = strtotime($updatedAt);
        if ($ts === false) {
            return '';
        }
        $mins = max(0, (int) floor((time() - $ts) / 60));
        if ($mins < 60) {
            return $mins . 'm';
        }
        return intdiv($mins, 60) . 'h ' . ($mins % 60) . 'm';
    }

    private function notice(?string $code): string
    {
        if ($code === null || !isset(self::NOTICES[$code])) {
            return '';
        }
        [$kind, $message] = self::NOTICES[$code];
        return '<div class="nb-notice nb-notice-' . ($kind === 'ok' ? 'ok' : 'err') . '">' . self::e($message) . '</div>';
    }

    private function autoRefresh(string $nonce): string
    {
        // The admin CSP is nonce-only for script-src; the handler is given the nonce
        // precisely so a page can run a small inline script (ADR 0020).
        return '<script nonce="' . self::e($nonce) . '">setTimeout(function(){location.reload();},15000);</script>';
    }

    private function styles(string $nonce): string
    {
        return '<style nonce="' . self::e($nonce) . '">'
            . '.kx-intro{max-width:60ch}'
            . '.kx-board{display:flex;gap:1rem;align-items:flex-start;overflow-x:auto;padding-bottom:.5rem}'
            . '.kx-col{flex:1 1 0;min-width:15rem;background:rgba(128,128,128,.06);border-radius:10px;padding:.6rem .6rem 1rem}'
            . '.kx-col h2{display:flex;align-items:center;gap:.4rem;font-size:1rem;margin:.2rem .2rem .6rem}'
            . '.kx-count{background:rgba(128,128,128,.2);border-radius:999px;padding:.05rem .5rem;font-size:.78rem}'
            . '.kx-col-sent{box-shadow:inset 4px 0 0 #c0392b}'
            . '.kx-col-preparing{box-shadow:inset 4px 0 0 #b8860b}'
            . '.kx-col-ready{box-shadow:inset 4px 0 0 #27ae60}'
            . '.kx-col-body{display:flex;flex-direction:column;gap:.6rem}'
            . '.kx-empty{margin:.3rem .2rem}'
            . '.kx-ticket{background:var(--nb-surface,#fff);border:1px solid rgba(128,128,128,.2);border-radius:8px;padding:.6rem .7rem}'
            . '.kx-ticket-top{display:flex;justify-content:space-between;align-items:baseline;gap:.5rem;margin-bottom:.4rem}'
            . '.kx-table{font-weight:800;font-size:1.05rem}'
            . '.kx-age{font-size:.75rem;opacity:.7;font-variant-numeric:tabular-nums}'
            . '.kx-items{list-style:none;margin:0 0 .5rem;padding:0;display:flex;flex-direction:column;gap:.15rem;font-size:.9rem}'
            . '.kx-qty{font-weight:700}'
            . '.kx-advance .nb-btn{width:100%;min-height:44px}'
            . '@media (max-width:52rem){.kx-board{flex-direction:column}.kx-col{width:100%;min-width:0}}'
            . '</style>';
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
