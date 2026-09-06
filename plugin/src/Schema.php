<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

/**
 * The Restaurant application's own tables (ADR 0005 — prefixed `rest_*`, away from
 * core `nb_*`). No restaurant-specific logic lives in Nimbus core; it all lives
 * here, in the app's plugin.
 *
 * Slice 1 is the **floor**: `rest_table`, the tables a restaurant seats guests at.
 * A table has a unique human label, a seat count, and a live status. Staff
 * assignment arrives with the Staff & roles slice (it needs Nimbus users first),
 * so it is deliberately absent here.
 */
final class Schema
{
    public const TABLE       = 'rest_table';
    public const ORDER       = 'rest_order';
    public const ORDER_ITEM  = 'rest_order_item';
    public const RESERVATION = 'rest_reservation';

    /** @return list<string> each statement individually idempotent (ADR 0005) */
    public static function tables(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . " (
                id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                label      VARCHAR(40) NOT NULL,
                seats      SMALLINT UNSIGNED NOT NULL DEFAULT 2,
                status     ENUM('open','occupied','dirty','reserved') NOT NULL DEFAULT 'open',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_table_label (label),
                INDEX idx_table_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }

    /**
     * Orders and their line items — the heart of service. An order sits on a table
     * and moves through a workflow (`open` → `sent` → `preparing` → `ready` →
     * `served` → `closed`); `paid` is orthogonal (payment is its own slice). Each
     * line **snapshots** the item's name and unit price at order time, so a later
     * menu edit never rewrites a bill. `menu_item_id` is a soft reference to the
     * `menu_items` collection entry a line came from (null for a manual line) — it
     * is a breadcrumb, never a join dependency, since the snapshot is authoritative.
     *
     * @return list<string> each statement individually idempotent (ADR 0005)
     */
    public static function orders(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS ' . self::ORDER . " (
                id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                table_id       BIGINT UNSIGNED NOT NULL,
                status         ENUM('open','sent','preparing','ready','served','closed') NOT NULL DEFAULT 'open',
                paid           TINYINT(1) NOT NULL DEFAULT 0,
                amount_paid    DECIMAL(10,2) NULL,
                payment_method VARCHAR(20) NULL,
                paid_at        DATETIME NULL,
                created_at     DATETIME NOT NULL,
                updated_at     DATETIME NOT NULL,
                INDEX idx_order_table (table_id),
                INDEX idx_order_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'CREATE TABLE IF NOT EXISTS ' . self::ORDER_ITEM . ' (
                id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id     BIGINT UNSIGNED NOT NULL,
                menu_item_id BIGINT UNSIGNED NULL,
                name         VARCHAR(200) NOT NULL,
                unit_price   DECIMAL(10,2) NOT NULL,
                qty          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                created_at   DATETIME NOT NULL,
                INDEX idx_item_order (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        ];
    }

    /**
     * Reservations — a booking of a table, at a time, for a party. `party_name` and
     * `notes` are the restaurant's **own** first-party data (what the host types when
     * taking the booking); `contact_id` is an optional link to the guest's full record
     * in the CRM (a separate plugin). The restaurant stores only that id and links out
     * to the CRM's own capability-gated page — it never reads or copies CRM contact
     * PII, so the CRM's gate is respected by construction. `table_id` is a soft ref to
     * a table (validated at write, same plugin).
     *
     * @return list<string> each statement individually idempotent (ADR 0005)
     */
    public static function reservations(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS ' . self::RESERVATION . " (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                table_id    BIGINT UNSIGNED NULL,
                contact_id  BIGINT UNSIGNED NULL,
                party_name  VARCHAR(120) NOT NULL,
                party_size  SMALLINT UNSIGNED NOT NULL DEFAULT 2,
                reserved_at DATETIME NOT NULL,
                status      ENUM('booked','seated','cancelled','no_show') NOT NULL DEFAULT 'booked',
                notes       TEXT NULL,
                created_at  DATETIME NOT NULL,
                updated_at  DATETIME NOT NULL,
                INDEX idx_res_at (reserved_at),
                INDEX idx_res_status (status),
                INDEX idx_res_table (table_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }
}
