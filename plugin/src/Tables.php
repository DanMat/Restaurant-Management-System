<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

use Nimbus\Plugin\PluginStorage;

/**
 * Tables — the restaurant floor. A thin service over {@see Schema::TABLE}, with the
 * write discipline carried from the CRM build:
 *
 *  - **Field allow-list** — a row is built only from known keys; `id`/timestamps are
 *    never mass-assigned.
 *  - **`status` is a write-time allow-list** ({@see STATUSES}), never interpolated.
 *  - **Unique label**, required and length-capped; **seats** a bounded positive int.
 *  - **Bound SQL everywhere**, including an escaped `LIKE` for label search.
 *  - **Store raw, escape on render** — the admin escapes on output.
 *
 * Access is capability-gated by the callers (admin page + MCP on
 * `danmat.restaurant:read|write`); this service holds no gate of its own.
 */
final class Tables
{
    /** @var list<string> the statuses a table can hold */
    public const STATUSES = ['open', 'occupied', 'dirty', 'reserved'];

    private const MAX_LABEL = 40;
    private const MAX_SEATS  = 999;

    /** @param \Closure():PluginStorage $storage resolved lazily, so construction runs no query */
    public function __construct(private \Closure $storage)
    {
    }

    /**
     * Create (id null) or update (id given) a table from an allow-listed field set;
     * unknown keys are ignored. Returns the table id.
     *
     * @param array<string,mixed> $fields
     */
    public function save(?int $id, array $fields, string $now): int
    {
        $existing = $id !== null ? $this->get($id) : null;
        if ($id !== null && $existing === null) {
            throw new \InvalidArgumentException("No table with id {$id}.");
        }

        $label  = $this->label($fields, $existing);
        $seats  = $this->seats($fields, $existing);
        $status = $this->status($fields, $existing);
        $this->requireUniqueLabel($label, $id);

        if ($id === null) {
            return $this->storage()->insert(
                'INSERT INTO ' . Schema::TABLE . ' (label, seats, status, created_at, updated_at)
                 VALUES (:label, :seats, :status, :created, :updated)',
                ['label' => $label, 'seats' => $seats, 'status' => $status, 'created' => $now, 'updated' => $now],
            );
        }

        $this->storage()->execute(
            'UPDATE ' . Schema::TABLE . ' SET label = :label, seats = :seats, status = :status, updated_at = :now WHERE id = :id',
            ['label' => $label, 'seats' => $seats, 'status' => $status, 'now' => $now, 'id' => $id],
        );
        return $id;
    }

    /**
     * Move a table to an allow-listed status (the floor's quick action). Returns the
     * number of rows changed (0 if the table doesn't exist).
     */
    public function setStatus(int $id, string $status, string $now): int
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('"status" must be one of: ' . implode(', ', self::STATUSES) . '.');
        }
        return $this->storage()->execute(
            'UPDATE ' . Schema::TABLE . ' SET status = :status, updated_at = :now WHERE id = :id',
            ['status' => $status, 'now' => $now, 'id' => $id],
        );
    }

    /**
     * @return array{id:int,label:string,seats:int,status:string,created_at:string,updated_at:string}|null
     */
    public function get(int $id): ?array
    {
        $row = $this->storage()->selectOne(
            'SELECT id, label, seats, status, created_at, updated_at FROM ' . Schema::TABLE . ' WHERE id = :id',
            ['id' => $id],
        );
        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * Tables for the floor board / MCP, optionally filtered to an allow-listed
     * `$status`. Ordered by label (natural-ish: shorter labels, then alpha).
     *
     * @return list<array{id:int,label:string,seats:int,status:string,created_at:string,updated_at:string}>
     */
    public function all(?string $status = null): array
    {
        if ($status !== null && $status !== '' && in_array($status, self::STATUSES, true)) {
            $rows = $this->storage()->select(
                'SELECT id, label, seats, status, created_at, updated_at FROM ' . Schema::TABLE . '
                 WHERE status = :status ORDER BY LENGTH(label), label',
                ['status' => $status],
            );
            return array_map($this->hydrate(...), $rows);
        }
        $rows = $this->storage()->select(
            'SELECT id, label, seats, status, created_at, updated_at FROM ' . Schema::TABLE . ' ORDER BY LENGTH(label), label',
        );
        return array_map($this->hydrate(...), $rows);
    }

    /** Delete a table outright by id; returns the number of rows removed (0 if none). */
    public function delete(int $id): int
    {
        return $this->storage()->execute('DELETE FROM ' . Schema::TABLE . ' WHERE id = :id', ['id' => $id]);
    }

    // --- validation / hydration -----------------------------------------

    /**
     * @param array<string,mixed>      $fields
     * @param array<string,mixed>|null $existing
     */
    private function label(array $fields, ?array $existing): string
    {
        if (!array_key_exists('label', $fields)) {
            if ($existing !== null) {
                return (string) $existing['label'];
            }
            throw new \InvalidArgumentException('A table needs a label.');
        }
        $label = trim((string) $fields['label']);
        if ($label === '') {
            throw new \InvalidArgumentException('A table needs a label.');
        }
        if (mb_strlen($label) > self::MAX_LABEL) {
            throw new \InvalidArgumentException('A table label must be ' . self::MAX_LABEL . ' characters or fewer.');
        }
        return $label;
    }

    /**
     * @param array<string,mixed>      $fields
     * @param array<string,mixed>|null $existing
     */
    private function seats(array $fields, ?array $existing): int
    {
        if (!array_key_exists('seats', $fields)) {
            return $existing !== null ? (int) $existing['seats'] : 2;
        }
        $raw = trim((string) $fields['seats']);
        if ($raw === '') {
            return 2;
        }
        if (preg_match('/^\d+$/', $raw) !== 1 || (int) $raw < 1 || (int) $raw > self::MAX_SEATS) {
            throw new \InvalidArgumentException('"seats" must be a whole number between 1 and ' . self::MAX_SEATS . '.');
        }
        return (int) $raw;
    }

    /**
     * @param array<string,mixed>      $fields
     * @param array<string,mixed>|null $existing
     */
    private function status(array $fields, ?array $existing): string
    {
        if (!array_key_exists('status', $fields)) {
            return $existing !== null ? (string) $existing['status'] : 'open';
        }
        $status = trim((string) $fields['status']);
        if ($status === '') {
            return $existing !== null ? (string) $existing['status'] : 'open';
        }
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('"status" must be one of: ' . implode(', ', self::STATUSES) . '.');
        }
        return $status;
    }

    /** A label is unique across the floor; reject a clash (excluding the row being updated). */
    private function requireUniqueLabel(string $label, ?int $id): void
    {
        $clash = $this->storage()->selectOne(
            'SELECT id FROM ' . Schema::TABLE . ' WHERE label = :label' . ($id !== null ? ' AND id <> :id' : ''),
            $id !== null ? ['label' => $label, 'id' => $id] : ['label' => $label],
        );
        if ($clash !== null) {
            throw new \InvalidArgumentException("A table labelled \"{$label}\" already exists.");
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,label:string,seats:int,status:string,created_at:string,updated_at:string}
     */
    private function hydrate(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'label'      => (string) $row['label'],
            'seats'      => (int) $row['seats'],
            'status'     => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    private function storage(): PluginStorage
    {
        return ($this->storage)();
    }
}
