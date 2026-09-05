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
    public function __construct(private Tables $tables)
    {
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
        ];
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
