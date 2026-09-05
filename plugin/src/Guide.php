<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

/**
 * The agent guide for the restaurant (ADR 0013), served as
 * `nimbus://guide/plugin/danmat.restaurant`. Reference documentation, not
 * instructions — static, bounded, world-readable to any valid token, so it
 * carries no secrets or per-tenant data.
 */
final class Guide
{
    public static function text(): string
    {
        return <<<'MD'
        # Restaurant

        The restaurant floor, orders, kitchen and reports — this application's own
        plugin. Everything is gated by the `danmat.restaurant` capability: a read
        needs `danmat.restaurant:read`, a write needs `danmat.restaurant:write`. A
        content `*:write` token cannot reach it, and a tool you lack the capability
        for is invisible.

        ## Tables (the floor)

        A table has a unique `label` (its number/name), a `seats` count, and a
        `status`: `open` (ready), `occupied` (a party is seated), `dirty` (needs
        cleaning), or `reserved`.

        - `restaurant_tables` — list tables, optionally filtered by `status`.
        - `restaurant_table_get` — one table by `id`.
        - `restaurant_table_set` — create (omit `id`) or update (with `id`). Fields:
          `label` (required to create), `seats`, `status`. Only the fields you send
          change.
        - `restaurant_table_status` — move a table to a status: seat a party
          (`occupied`), clear it (`dirty`), turn it (`open`), or hold it
          (`reserved`).
        - `restaurant_table_delete` — remove a table by `id`.

        Values are stored as you send them and escaped when displayed.
        MD;
    }
}
