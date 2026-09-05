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
    public const TABLE = 'rest_table';

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
}
