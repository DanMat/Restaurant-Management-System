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
        plugin. Everything is gated by the `danmat.restaurant` capability, and a
        content `*:write` token cannot reach it (a tool you lack the capability for is
        invisible).

        ## Staff roles

        Staff are Nimbus users granted fine-grained actions of `danmat.restaurant`:

        - `danmat.restaurant:floor` — the floor: tables, orders and taking payment
          (waiters, hosts, busboys).
        - `danmat.restaurant:kitchen` — the kitchen display (cooks).
        - `danmat.restaurant:manage` — reports and settings (managers/admins, who
          usually also hold floor + kitchen).
        - `danmat.restaurant:read` / `:write` — this MCP surface (an agent or
          integration). Each action is an independent, wildcard-immune grant, so a
          cook (`:kitchen`) cannot take payment and a waiter (`:floor`) cannot be
          handed the books.

        Amounts are strings with two decimals; order totals are always computed from
        the line items, never set directly.

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

        ## Menu & orders

        The menu is a Nimbus collection; read it with `restaurant_menu` (each item has
        an id, name and price). An order lives on a table and moves through a workflow:
        `open` → `sent` (to the kitchen) → `preparing` → `ready` → `served` → `closed`.

        - `restaurant_order_open` — open an order on a table (which becomes occupied).
        - `restaurant_orders` — list orders, filter by `status` and/or `table_id`.
        - `restaurant_order_get` — one order with its line items and computed total.
        - `restaurant_order_status` — advance the workflow.
        - `restaurant_order_add_item` — add a line: give a `menu_item_id` to add from
          the menu (its name + price are snapshotted), or a `name` + `price` for a
          manual line, plus a `qty`.
        - `restaurant_order_set_item_qty` — change a line's quantity (0 removes it).
        - `restaurant_order_remove_item` — remove a line.
        - `restaurant_order_pay` — take payment and turn the table. You choose only the
          `method` (`cash`/`card`/`other`); the amount charged is the computed order
          total, never passed in. This marks the order paid, closes it, and sets its
          table `dirty` for bussing (the floor then cleans it back to `open`).
        - `restaurant_order_delete` — delete an order and its lines.

        A line snapshots the item's name and price when added, so editing the menu
        later never changes an existing order.

        ## Kitchen

        - `restaurant_kitchen` — the kitchen queue: orders in `sent`, `preparing` or
          `ready`, oldest first, each with its items. Advance a ticket by setting its
          status with `restaurant_order_status`: `sent` → `preparing` (started) →
          `ready` (up for the pass). The floor then marks it `served`.
        MD;
    }
}
