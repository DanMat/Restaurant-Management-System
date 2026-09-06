<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

/**
 * The reservation book — a list of bookings and a create/edit form. It shows the
 * restaurant's own booking data (party name/size/time/notes) and, when a booking is
 * linked to a CRM guest, a **link out** to that guest's CRM page — it never displays
 * CRM contact data itself, so the CRM's capability gate is respected: core decides
 * whether the viewer may open the linked record. Same admin discipline as the other
 * terminals (escape-on-render, nonce'd `<style>`, `danmat.restaurant:floor` + CSRF).
 */
final class ReservationsAdmin
{
    private const NOTICES = [
        'saved'   => ['ok', 'Reservation saved.'],
        'updated' => ['ok', 'Reservation updated.'],
        'deleted' => ['ok', 'Reservation deleted.'],
        'noname'  => ['err', 'A reservation needs a party name.'],
        'invalid' => ['err', 'Check the details and try again.'],
    ];

    private const STATUS_LABELS = [
        'booked'    => 'Booked',
        'seated'    => 'Seated',
        'cancelled' => 'Cancelled',
        'no_show'   => 'No-show',
    ];

    public function __construct(private Reservations $reservations, private Tables $tables)
    {
    }

    public function render(string $csrf = '', ?string $notice = null, ?string $edit = null, ?string $status = null, string $nonce = ''): string
    {
        $editId  = ($edit !== null && preg_match('/^\d+$/', trim($edit)) === 1) ? (int) trim($edit) : null;
        $editRes = $editId !== null ? $this->reservations->get($editId) : null;
        $filter  = ($status !== null && in_array(trim($status), Reservations::STATUSES, true)) ? trim($status) : null;

        return $this->styles($nonce)
            . Branding::head('Reservations', 'The book — upcoming bookings.', $nonce)
            . $this->notice($notice)
            . '<p class="nb-muted rz-intro">Upcoming bookings. Linking a guest connects the booking to their record in the CRM — opening it needs CRM access.</p>'
            . $this->form($csrf, $editRes)
            . $this->filterBar($filter)
            . $this->list($csrf, $this->reservations->all($filter));
    }

    /** @param array<string,mixed>|null $edit */
    private function form(string $csrf, ?array $edit): string
    {
        $val     = static fn (string $k): string => $edit !== null && $edit[$k] !== null ? self::e((string) $edit[$k]) : '';
        $idField = $edit !== null ? '<input type="hidden" name="id" value="' . self::e((string) $edit['id']) . '">' : '';
        // reserved_at into a datetime-local value (drop seconds).
        $at = $edit !== null ? substr(str_replace(' ', 'T', (string) $edit['reserved_at']), 0, 16) : '';

        $statusOptions = '';
        $current       = $edit !== null ? (string) $edit['status'] : 'booked';
        foreach (Reservations::STATUSES as $s) {
            $statusOptions .= '<option value="' . self::e($s) . '"' . ($s === $current ? ' selected' : '') . '>' . self::e(self::STATUS_LABELS[$s]) . '</option>';
        }

        $tableOptions = '<option value="">— no table yet —</option>';
        $curTable     = $edit !== null && $edit['table_id'] !== null ? (int) $edit['table_id'] : null;
        foreach ($this->tables->all() as $t) {
            $tableOptions .= '<option value="' . self::e((string) $t['id']) . '"' . ($curTable === (int) $t['id'] ? ' selected' : '') . '>' . self::e((string) $t['label']) . '</option>';
        }

        return '<h2>' . ($edit !== null ? 'Edit reservation' : 'Add a reservation') . '</h2>'
            . '<form method="post" action="/admin/restaurant-reservations/reservation-save" class="rz-form">'
            . '<input type="hidden" name="_csrf" value="' . self::e($csrf) . '">' . $idField
            . '<div class="rz-row">'
            . '<label>Party name<input type="text" name="party_name" value="' . $val('party_name') . '" maxlength="120" required></label>'
            . '<label>Party size<input type="number" name="party_size" value="' . ($edit !== null ? $val('party_size') : '2') . '" min="1" max="99"></label>'
            . '</div>'
            . '<div class="rz-row">'
            . '<label>When<input type="datetime-local" name="reserved_at" value="' . self::e($at) . '"></label>'
            . '<label>Table<select name="table_id">' . $tableOptions . '</select></label>'
            . '<label>Status<select name="status">' . $statusOptions . '</select></label>'
            . '</div>'
            . '<label>Guest (CRM contact id)<input type="number" name="contact_id" value="' . $val('contact_id') . '" min="1" placeholder="optional — links to the CRM"></label>'
            . '<label>Notes<textarea name="notes" rows="2" maxlength="10000" placeholder="Allergies, occasion, seating…">' . $val('notes') . '</textarea></label>'
            . '<div class="rz-actions"><button type="submit" class="nb-btn">' . ($edit !== null ? 'Save reservation' : 'Add reservation') . '</button>'
            . ($edit !== null ? ' <a class="nb-btn nb-btn-quiet" href="/admin/restaurant-reservations">Cancel</a>' : '')
            . '</div></form>';
    }

    private function filterBar(?string $active): string
    {
        $chips = '<a class="rz-chip' . ($active === null ? ' is-active' : '') . '" href="/admin/restaurant-reservations">All</a>';
        foreach (Reservations::STATUSES as $s) {
            $chips .= '<a class="rz-chip' . ($active === $s ? ' is-active' : '') . '" href="/admin/restaurant-reservations?status=' . self::e($s) . '">' . self::e(self::STATUS_LABELS[$s]) . '</a>';
        }
        return '<div class="rz-filter" role="group" aria-label="Filter by status">' . $chips . '</div>';
    }

    /** @param list<array<string,mixed>> $reservations */
    private function list(string $csrf, array $reservations): string
    {
        if ($reservations === []) {
            return '<p class="nb-muted">No reservations.</p>';
        }
        $rows = '';
        foreach ($reservations as $r) {
            $guest = ($r['contact_id'] ?? null) !== null
                ? '<a href="/admin/crm?edit=' . self::e((string) $r['contact_id']) . '">Guest in CRM →</a>'
                : '<span class="nb-muted">—</span>';
            $rows .= '<tr>'
                . '<td data-label="When">' . self::e((string) $r['reserved_at']) . '</td>'
                . '<td data-label="Party"><a href="/admin/restaurant-reservations?edit=' . self::e((string) $r['id']) . '">' . self::e((string) $r['party_name']) . '</a> · ' . self::e((string) $r['party_size']) . '</td>'
                . '<td data-label="Table">' . self::e((string) ($r['table_label'] ?? '—')) . '</td>'
                . '<td data-label="Status"><span class="rz-badge rz-badge-' . self::e((string) $r['status']) . '">' . self::e(self::STATUS_LABELS[(string) $r['status']] ?? (string) $r['status']) . '</span></td>'
                . '<td data-label="Guest">' . $guest . '</td>'
                . '<td data-label="" class="rz-rowact">'
                . '<form method="post" action="/admin/restaurant-reservations/reservation-delete" data-confirm="Delete this reservation?">'
                . '<input type="hidden" name="_csrf" value="' . self::e($csrf) . '">'
                . '<input type="hidden" name="id" value="' . self::e((string) $r['id']) . '">'
                . '<button type="submit" class="rz-link-danger">Delete</button></form></td>'
                . '</tr>';
        }
        return '<table class="rz-table"><thead><tr><th>When</th><th>Party</th><th>Table</th><th>Status</th><th>Guest</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table>';
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
            . '.rz-form{max-width:44rem;display:flex;flex-direction:column;gap:.75rem;margin:0 0 1.5rem}'
            . '.rz-row{display:flex;gap:.75rem;flex-wrap:wrap}'
            . '.rz-form label{display:flex;flex-direction:column;gap:.25rem;flex:1 1 10rem;font-weight:600;font-size:.85rem}'
            . '.rz-form input,.rz-form select,.rz-form textarea{font:inherit;padding:.5rem .6rem;min-height:44px;box-sizing:border-box}'
            . '.rz-filter{display:flex;flex-wrap:wrap;gap:.4rem;margin:0 0 1rem}'
            . '.rz-chip{text-decoration:none;background:rgba(128,128,128,.12);border-radius:999px;padding:.3rem .8rem;font-size:.82rem;color:inherit;min-height:32px;display:inline-flex;align-items:center}'
            . '.rz-chip.is-active{background:rgba(52,152,219,.22);color:#2471a3;font-weight:700}'
            . '.rz-table{width:100%;border-collapse:collapse}'
            . '.rz-table th,.rz-table td{text-align:left;padding:.55rem .5rem;border-bottom:1px solid rgba(128,128,128,.2)}'
            . '.rz-badge{font-size:.68rem;font-weight:700;padding:.12rem .5rem;border-radius:999px;text-transform:uppercase;letter-spacing:.03em;background:rgba(128,128,128,.16)}'
            . '.rz-badge-booked{background:rgba(41,128,185,.18);color:#2471a3}'
            . '.rz-badge-seated{background:rgba(39,174,96,.18);color:#1e8449}'
            . '.rz-badge-cancelled{background:rgba(120,120,120,.15);opacity:.85}'
            . '.rz-badge-no_show{background:rgba(192,57,43,.15);color:#c0392b}'
            . '.rz-rowact{text-align:right}'
            . '.rz-link-danger{background:none;border:0;color:#c0392b;font:inherit;cursor:pointer;text-decoration:underline;padding:.25rem 0;min-height:36px}'
            . '@media (max-width:44rem){'
            . '.rz-table,.rz-table tbody,.rz-table tr,.rz-table td{display:block}'
            . '.rz-table thead{display:none}'
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
