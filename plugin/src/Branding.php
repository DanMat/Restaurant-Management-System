<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

/**
 * The shared RAS identity for the staff terminals — the small "Restaurant
 * Automation System" eyebrow above each page title, carried over (uplifted) from
 * the original app so every terminal reads as one system. The styles ride in a
 * nonce-carrying `<style>` block because the admin CSP drops inline `style=`.
 */
final class Branding
{
    /** The plugin's shared status palette (uplifted from the original RAS colours). */
    public const STATUS_COLORS = [
        'open'     => '#3d8b40',
        'occupied' => '#b8860b',
        'dirty'    => '#c0392b',
        'reserved' => '#2471a3',
    ];

    /** A page header with the RAS eyebrow, a title, and a one-line subtitle. */
    public static function head(string $title, string $subtitle, string $nonce): string
    {
        return '<style nonce="' . self::e($nonce) . '">'
            . '.rs-head{margin:0 0 1rem}'
            . '.rs-eyebrow{margin:0;font-size:.72rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#b8860b}'
            . '.rs-head h1{margin:.15rem 0 .1rem}'
            . '.rs-sub{margin:0;font-size:.9rem;color:var(--nb-muted,#6b7280)}'
            . '</style>'
            . '<div class="nb-page-head rs-head">'
            . '<p class="rs-eyebrow">RAS · Restaurant Automation System</p>'
            . '<h1>' . self::e($title) . '</h1>'
            . '<p class="rs-sub">' . self::e($subtitle) . '</p>'
            . '</div>';
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
