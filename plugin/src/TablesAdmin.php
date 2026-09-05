<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

/**
 * The floor — a board of tables by status, plus a create/edit form. Every table
 * card carries the quick actions that move it through the service cycle
 * (seat → clear → clean, or reserve). Same admin discipline as the CRM pages:
 * every author value escaped on output ({@see e}), styling in one nonce-carrying
 * `<style>` block (the admin CSP is nonce-only), and the page + its POST actions
 * gated on `danmat.restaurant:write` + CSRF by core (ADR 0020). Built mobile-first
 * — staff work this on a phone.
 */
final class TablesAdmin
{
    private const NOTICES = [
        'saved'    => ['ok', 'Table saved.'],
        'deleted'  => ['ok', 'Table deleted.'],
        'seated'   => ['ok', 'Table updated.'],
        'nolabel'  => ['err', 'A table needs a label.'],
        'dupe'     => ['err', 'A table with that label already exists.'],
        'invalid'  => ['err', 'Check the details and try again.'],
    ];

    /** status => [label, quick-action verb offered on the card] */
    private const STATUS_LABELS = [
        'open'     => 'Open',
        'occupied' => 'Occupied',
        'dirty'    => 'Needs cleaning',
        'reserved' => 'Reserved',
    ];

    public function __construct(private Tables $tables)
    {
    }

    public function render(string $csrf = '', ?string $notice = null, ?string $edit = null, ?string $status = null, string $nonce = ''): string
    {
        $editId    = ($edit !== null && preg_match('/^\d+$/', trim($edit)) === 1) ? (int) trim($edit) : null;
        $editTable = $editId !== null ? $this->tables->get($editId) : null;
        $filter    = ($status !== null && in_array(trim($status), Tables::STATUSES, true)) ? trim($status) : null;

        return $this->styles($nonce)
            . '<div class="nb-page-head"><h1>Floor</h1></div>'
            . $this->notice($notice)
            . '<p class="nb-muted rz-intro">Your tables and their status. Seat guests, send tables for cleaning, and turn them for the next party.</p>'
            . $this->form($csrf, $editTable)
            . $this->filterBar($filter)
            . $this->board($csrf, $this->tables->all($filter), $filter);
    }

    /** @param array<string,mixed>|null $edit */
    private function form(string $csrf, ?array $edit): string
    {
        $val     = static fn (string $k): string => $edit !== null && $edit[$k] !== null ? self::e((string) $edit[$k]) : '';
        $idField = $edit !== null ? '<input type="hidden" name="id" value="' . self::e((string) $edit['id']) . '">' : '';

        $statusOptions = '';
        $current       = $edit !== null ? (string) $edit['status'] : 'open';
        foreach (Tables::STATUSES as $s) {
            $statusOptions .= '<option value="' . self::e($s) . '"' . ($s === $current ? ' selected' : '') . '>' . self::e(self::STATUS_LABELS[$s]) . '</option>';
        }

        return '<h2>' . ($edit !== null ? 'Edit table' : 'Add a table') . '</h2>'
            . '<form method="post" action="/admin/restaurant/table-save" class="rz-form">'
            . '<input type="hidden" name="_csrf" value="' . self::e($csrf) . '">' . $idField
            . '<div class="rz-row">'
            . '<label>Label<input type="text" name="label" value="' . $val('label') . '" maxlength="40" required></label>'
            . '<label>Seats<input type="number" name="seats" value="' . ($edit !== null ? $val('seats') : '2') . '" min="1" max="999"></label>'
            . '<label>Status<select name="status">' . $statusOptions . '</select></label>'
            . '</div>'
            . '<div class="rz-actions"><button type="submit" class="nb-btn">' . ($edit !== null ? 'Save table' : 'Add table') . '</button>'
            . ($edit !== null ? ' <a class="nb-btn nb-btn-quiet" href="/admin/restaurant">Cancel</a>' : '')
            . '</div></form>';
    }

    private function filterBar(?string $active): string
    {
        $chips = '<a class="rz-chip' . ($active === null ? ' is-active' : '') . '" href="/admin/restaurant">All</a>';
        foreach (Tables::STATUSES as $s) {
            $chips .= '<a class="rz-chip' . ($active === $s ? ' is-active' : '') . '" href="/admin/restaurant?status=' . self::e($s) . '">' . self::e(self::STATUS_LABELS[$s]) . '</a>';
        }
        return '<div class="rz-filter" role="group" aria-label="Filter by status">' . $chips . '</div>';
    }

    /**
     * @param list<array<string,mixed>> $tables
     */
    private function board(string $csrf, array $tables, ?string $filter): string
    {
        if ($tables === []) {
            $msg = $filter !== null ? 'No ' . self::e(self::STATUS_LABELS[$filter]) . ' tables.' : 'No tables yet — add one above.';
            return '<p class="nb-muted">' . $msg . '</p>';
        }

        $cards = '';
        foreach ($tables as $t) {
            $status = (string) $t['status'];
            $cards .= '<li class="rz-table rz-status-' . self::e($status) . '">'
                . '<div class="rz-table-top">'
                . '<a class="rz-table-label" href="/admin/restaurant?edit=' . self::e((string) $t['id']) . '">' . self::e((string) $t['label']) . '</a>'
                . '<span class="rz-badge rz-badge-' . self::e($status) . '">' . self::e(self::STATUS_LABELS[$status] ?? $status) . '</span>'
                . '</div>'
                . '<div class="rz-table-seats">' . self::e((string) $t['seats']) . ' seats</div>'
                . '<div class="rz-table-acts">' . $this->actions($csrf, (int) $t['id'], $status) . '</div>'
                . '</li>';
        }

        return '<ul class="rz-board">' . $cards . '</ul>';
    }

    /** The status quick-actions relevant to a table's current state. */
    private function actions(string $csrf, int $id, string $status): string
    {
        // What each button moves the table to, shown only when it makes sense.
        $moves = [];
        if ($status !== 'occupied') {
            $moves[] = ['occupied', 'Seat'];
        }
        if ($status === 'occupied') {
            $moves[] = ['dirty', 'Clear'];
        }
        if ($status === 'dirty' || $status === 'reserved') {
            $moves[] = ['open', 'Open'];
        }
        if ($status === 'open') {
            $moves[] = ['reserved', 'Reserve'];
        }

        $html = '';
        foreach ($moves as [$to, $verb]) {
            $html .= '<form method="post" action="/admin/restaurant/table-status" class="rz-act">'
                . '<input type="hidden" name="_csrf" value="' . self::e($csrf) . '">'
                . '<input type="hidden" name="id" value="' . self::e((string) $id) . '">'
                . '<input type="hidden" name="status" value="' . self::e($to) . '">'
                . '<button type="submit" class="nb-btn nb-btn-quiet rz-act-btn">' . self::e($verb) . '</button></form>';
        }

        $html .= '<form method="post" action="/admin/restaurant/table-delete" class="rz-act" data-confirm="Delete this table?">'
            . '<input type="hidden" name="_csrf" value="' . self::e($csrf) . '">'
            . '<input type="hidden" name="id" value="' . self::e((string) $id) . '">'
            . '<button type="submit" class="rz-link-danger">Delete</button></form>';

        return $html;
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
            . '.rz-intro{max-width:60ch}'
            . '.rz-form{max-width:40rem;display:flex;flex-direction:column;gap:.75rem;margin:0 0 1.5rem}'
            . '.rz-row{display:flex;gap:.75rem;flex-wrap:wrap}'
            . '.rz-form label{display:flex;flex-direction:column;gap:.25rem;flex:1 1 8rem;font-weight:600;font-size:.85rem}'
            . '.rz-form input,.rz-form select{font:inherit;padding:.5rem .6rem;min-height:44px;box-sizing:border-box}'
            . '.rz-filter{display:flex;flex-wrap:wrap;gap:.4rem;margin:0 0 1rem}'
            . '.rz-chip{text-decoration:none;background:rgba(128,128,128,.12);border-radius:999px;padding:.3rem .8rem;font-size:.82rem;color:inherit;min-height:32px;display:inline-flex;align-items:center}'
            . '.rz-chip.is-active{background:rgba(52,152,219,.22);color:#2471a3;font-weight:700}'
            . '.rz-board{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(9.5rem,1fr));gap:.75rem}'
            . '.rz-table{border:1px solid rgba(128,128,128,.2);border-left:4px solid rgba(128,128,128,.4);border-radius:10px;padding:.6rem .7rem;display:flex;flex-direction:column;gap:.4rem}'
            . '.rz-status-open{border-left-color:#27ae60}'
            . '.rz-status-occupied{border-left-color:#2980b9}'
            . '.rz-status-dirty{border-left-color:#c0392b}'
            . '.rz-status-reserved{border-left-color:#b8860b}'
            . '.rz-table-top{display:flex;justify-content:space-between;align-items:center;gap:.4rem}'
            . '.rz-table-label{font-weight:800;font-size:1.15rem;text-decoration:none}'
            . '.rz-table-seats{font-size:.8rem;opacity:.7}'
            . '.rz-badge{font-size:.65rem;font-weight:700;padding:.1rem .4rem;border-radius:999px;text-transform:uppercase;letter-spacing:.03em}'
            . '.rz-badge-open{background:rgba(39,174,96,.18);color:#1e8449}'
            . '.rz-badge-occupied{background:rgba(41,128,185,.18);color:#2471a3}'
            . '.rz-badge-dirty{background:rgba(192,57,43,.15);color:#c0392b}'
            . '.rz-badge-reserved{background:rgba(184,134,11,.18);color:#9a7d0a}'
            . '.rz-table-acts{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center;margin-top:.1rem}'
            . '.rz-act{display:inline}'
            . '.rz-act-btn{min-height:36px;padding:.25rem .6rem;font-size:.8rem}'
            . '.rz-link-danger{background:none;border:0;color:#c0392b;font:inherit;cursor:pointer;text-decoration:underline;padding:.25rem 0;min-height:36px}'
            . '</style>';
    }

    /** Escape a value for HTML output (the admin CSP is nonce-only; every value is escaped). */
    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
