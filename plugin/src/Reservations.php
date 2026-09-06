<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

use Nimbus\Plugin\PluginStorage;

/**
 * Reservations — a booking of a table, at a time, for a party.
 *
 * The **CRM boundary** is the sharp edge here (the security review's rule): a
 * reservation carries the restaurant's *own* first-party data — `party_name`,
 * `party_size`, `notes`, entered by the host — plus an optional `contact_id` that
 * merely **links** to the guest's full record in the CRM (a separate plugin). This
 * service never reads, resolves or copies CRM contact PII; the admin links out to
 * the CRM's own capability-gated page. So a floor user without `nimbuscms.crm:read`
 * can run the book but cannot see a guest's CRM record, and no CRM PII is ever
 * laundered into `rest_*` tables. Consequently `contact_id` is stored as given and
 * not validated to exist (that would require reading the CRM); a stale link simply
 * resolves to nothing on the CRM side.
 *
 * Same discipline otherwise: field allow-list, write-time status allow-list, bound
 * SQL, `table_id` validated (same plugin), total delete.
 */
final class Reservations
{
    /** @var list<string> the reservation lifecycle */
    public const STATUSES = ['booked', 'seated', 'cancelled', 'no_show'];

    private const MAX_NAME  = 120;
    private const MAX_NOTES = 10000;
    private const MAX_PARTY = 99;

    /** @param \Closure():PluginStorage $storage resolved lazily, so construction runs no query */
    public function __construct(private \Closure $storage, private Tables $tables)
    {
    }

    /**
     * Create (id null) or update (id given) a reservation from an allow-listed field
     * set. Returns the reservation id.
     *
     * @param array<string,mixed> $fields
     */
    public function save(?int $id, array $fields, string $now): int
    {
        $existing = $id !== null ? $this->get($id) : null;
        if ($id !== null && $existing === null) {
            throw new \InvalidArgumentException("No reservation with id {$id}.");
        }

        $name      = $this->name($fields, $existing);
        $size      = $this->partySize($fields, $existing);
        $at        = $this->reservedAt($fields, $existing, $now);
        $status    = $this->status($fields, $existing);
        $tableId   = $this->tableId($fields, $existing);
        $contactId = $this->contactId($fields, $existing);
        $notes     = $this->optStr($fields, 'notes', $existing, self::MAX_NOTES);

        $params = ['name' => $name, 'size' => $size, 'at' => $at, 'status' => $status, 'table' => $tableId, 'contact' => $contactId, 'notes' => $notes];

        if ($id === null) {
            return $this->storage()->insert(
                'INSERT INTO ' . Schema::RESERVATION . ' (party_name, party_size, reserved_at, status, table_id, contact_id, notes, created_at, updated_at)
                 VALUES (:name, :size, :at, :status, :table, :contact, :notes, :created, :updated)',
                $params + ['created' => $now, 'updated' => $now],
            );
        }

        $this->storage()->execute(
            'UPDATE ' . Schema::RESERVATION . ' SET party_name = :name, party_size = :size, reserved_at = :at, status = :status, table_id = :table, contact_id = :contact, notes = :notes, updated_at = :updated WHERE id = :id',
            $params + ['updated' => $now, 'id' => $id],
        );
        return $id;
    }

    /** Move a reservation to an allow-listed status. Returns rows changed. */
    public function setStatus(int $id, string $status, string $now): int
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('"status" must be one of: ' . implode(', ', self::STATUSES) . '.');
        }
        return $this->storage()->execute(
            'UPDATE ' . Schema::RESERVATION . ' SET status = :status, updated_at = :now WHERE id = :id',
            ['status' => $status, 'now' => $now, 'id' => $id],
        );
    }

    /**
     * @return array{id:int,table_id:?int,table_label:?string,contact_id:?int,party_name:string,party_size:int,reserved_at:string,status:string,notes:?string,created_at:string,updated_at:string}|null
     */
    public function get(int $id): ?array
    {
        $row = $this->storage()->selectOne(
            $this->selectExpr() . ' WHERE r.id = :id',
            ['id' => $id],
        );
        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * Reservations for the book / MCP, soonest first, optionally filtered by an
     * allow-listed status.
     *
     * @return list<array{id:int,table_id:?int,table_label:?string,contact_id:?int,party_name:string,party_size:int,reserved_at:string,status:string,notes:?string,created_at:string,updated_at:string}>
     */
    public function all(?string $status = null): array
    {
        if ($status !== null && $status !== '' && in_array($status, self::STATUSES, true)) {
            $rows = $this->storage()->select(
                $this->selectExpr() . ' WHERE r.status = :status ORDER BY r.reserved_at ASC, r.id ASC',
                ['status' => $status],
            );
            return array_map($this->hydrate(...), $rows);
        }
        return array_map($this->hydrate(...), $this->storage()->select($this->selectExpr() . ' ORDER BY r.reserved_at ASC, r.id ASC'));
    }

    /** Delete a reservation outright by id; returns rows removed. */
    public function delete(int $id): int
    {
        return $this->storage()->execute('DELETE FROM ' . Schema::RESERVATION . ' WHERE id = :id', ['id' => $id]);
    }

    // --- validation / hydration -----------------------------------------

    private function selectExpr(): string
    {
        return 'SELECT r.id, r.table_id, r.contact_id, r.party_name, r.party_size, r.reserved_at, r.status, r.notes, r.created_at, r.updated_at,
                       t.label AS table_label
                FROM ' . Schema::RESERVATION . ' r LEFT JOIN ' . Schema::TABLE . ' t ON t.id = r.table_id';
    }

    /**
     * @param array<string,mixed>      $fields
     * @param array<string,mixed>|null $existing
     */
    private function name(array $fields, ?array $existing): string
    {
        if (!array_key_exists('party_name', $fields)) {
            if ($existing !== null) {
                return (string) $existing['party_name'];
            }
            throw new \InvalidArgumentException('A reservation needs a party name.');
        }
        $name = trim((string) $fields['party_name']);
        if ($name === '') {
            throw new \InvalidArgumentException('A reservation needs a party name.');
        }
        if (mb_strlen($name) > self::MAX_NAME) {
            throw new \InvalidArgumentException('A party name must be ' . self::MAX_NAME . ' characters or fewer.');
        }
        return $name;
    }

    /**
     * @param array<string,mixed>      $fields
     * @param array<string,mixed>|null $existing
     */
    private function partySize(array $fields, ?array $existing): int
    {
        if (!array_key_exists('party_size', $fields)) {
            return $existing !== null ? (int) $existing['party_size'] : 2;
        }
        $raw = trim((string) $fields['party_size']);
        if ($raw === '') {
            return 2;
        }
        if (preg_match('/^\d+$/', $raw) !== 1 || (int) $raw < 1 || (int) $raw > self::MAX_PARTY) {
            throw new \InvalidArgumentException('"party_size" must be a whole number between 1 and ' . self::MAX_PARTY . '.');
        }
        return (int) $raw;
    }

    /**
     * When the booking is for. Accepts a full datetime or an `datetime-local` value;
     * absent on a create defaults to now. A sloppy value is rejected.
     *
     * @param array<string,mixed>      $fields
     * @param array<string,mixed>|null $existing
     */
    private function reservedAt(array $fields, ?array $existing, string $now): string
    {
        if (!array_key_exists('reserved_at', $fields)) {
            return $existing !== null ? (string) $existing['reserved_at'] : $now;
        }
        $raw = str_replace('T', ' ', trim((string) $fields['reserved_at']));
        if ($raw === '') {
            return $existing !== null ? (string) $existing['reserved_at'] : $now;
        }
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $fmt) {
            $d = \DateTimeImmutable::createFromFormat($fmt, $raw);
            if ($d !== false && $d->format($fmt) === $raw) {
                return $d->format('Y-m-d H:i:s');
            }
        }
        throw new \InvalidArgumentException('"reserved_at" must be a valid date and time.');
    }

    /**
     * @param array<string,mixed>      $fields
     * @param array<string,mixed>|null $existing
     */
    private function status(array $fields, ?array $existing): string
    {
        if (!array_key_exists('status', $fields)) {
            return $existing !== null ? (string) $existing['status'] : 'booked';
        }
        $status = trim((string) $fields['status']);
        if ($status === '') {
            return $existing !== null ? (string) $existing['status'] : 'booked';
        }
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('"status" must be one of: ' . implode(', ', self::STATUSES) . '.');
        }
        return $status;
    }

    /**
     * The table a reservation holds: null, or an id that must exist (same-plugin soft
     * ref, validated at write). Absent on update keeps the stored value.
     *
     * @param array<string,mixed>      $fields
     * @param array<string,mixed>|null $existing
     */
    private function tableId(array $fields, ?array $existing): ?int
    {
        if (!array_key_exists('table_id', $fields)) {
            return $existing !== null ? ($existing['table_id'] === null ? null : (int) $existing['table_id']) : null;
        }
        $raw = trim((string) $fields['table_id']);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $raw) !== 1 || (int) $raw < 1) {
            throw new \InvalidArgumentException('"table_id" must be a positive whole number or blank.');
        }
        $tableId = (int) $raw;
        if ($this->tables->get($tableId) === null) {
            throw new \InvalidArgumentException("No table with id {$tableId}.");
        }
        return $tableId;
    }

    /**
     * The guest's CRM contact id: null, or a positive int. **Not** validated to exist
     * — that lives in the CRM plugin, and reading it would breach the CRM's capability
     * gate (see the class docblock). It is a link, resolved (or not) on the CRM side.
     *
     * @param array<string,mixed>      $fields
     * @param array<string,mixed>|null $existing
     */
    private function contactId(array $fields, ?array $existing): ?int
    {
        if (!array_key_exists('contact_id', $fields)) {
            return $existing !== null ? ($existing['contact_id'] === null ? null : (int) $existing['contact_id']) : null;
        }
        $raw = trim((string) $fields['contact_id']);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $raw) !== 1 || (int) $raw < 1) {
            throw new \InvalidArgumentException('"contact_id" must be a positive whole number or blank.');
        }
        return (int) $raw;
    }

    /**
     * @param array<string,mixed>      $fields
     * @param array<string,mixed>|null $existing
     */
    private function optStr(array $fields, string $key, ?array $existing, int $max): ?string
    {
        if (!array_key_exists($key, $fields)) {
            return $existing !== null ? ($existing[$key] === null ? null : (string) $existing[$key]) : null;
        }
        $v = trim((string) $fields[$key]);
        if ($v === '') {
            return null;
        }
        if (mb_strlen($v) > $max) {
            throw new \InvalidArgumentException("\"{$key}\" must be {$max} characters or fewer.");
        }
        return $v;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,table_id:?int,table_label:?string,contact_id:?int,party_name:string,party_size:int,reserved_at:string,status:string,notes:?string,created_at:string,updated_at:string}
     */
    private function hydrate(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'table_id'    => $row['table_id'] === null ? null : (int) $row['table_id'],
            'table_label' => ($row['table_label'] ?? null) === null ? null : (string) $row['table_label'],
            'contact_id'  => $row['contact_id'] === null ? null : (int) $row['contact_id'],
            'party_name'  => (string) $row['party_name'],
            'party_size'  => (int) $row['party_size'],
            'reserved_at' => (string) $row['reserved_at'],
            'status'      => (string) $row['status'],
            'notes'       => $row['notes'] === null ? null : (string) $row['notes'],
            'created_at'  => (string) $row['created_at'],
            'updated_at'  => (string) $row['updated_at'],
        ];
    }

    private function storage(): PluginStorage
    {
        return ($this->storage)();
    }
}
