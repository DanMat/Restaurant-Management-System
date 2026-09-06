<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

use Nimbus\Plugin\PluginStorage;

/**
 * A tiny per-IP fixed-window rate limiter for the public online-order endpoint —
 * enough to blunt a scripted flood on a demo that also resets hourly and sits
 * behind Cloudflare, not a general-purpose throttle. One row per client IP: the
 * first request in a window starts a counter, the (limit+1)th within the window is
 * refused, and the window resets once it expires. Stored in the plugin's own table
 * ({@see Schema::ORDER_RATE}); the value is just an IP + a count, wiped by the reset.
 */
final class RateLimiter
{
    /** @param \Closure():PluginStorage $storage */
    public function __construct(
        private \Closure $storage,
        private int $limit = 5,
        private int $windowSeconds = 60,
    ) {
    }

    /** Record an attempt from $ip at $now; true if allowed, false if over the limit. */
    public function allow(string $ip, string $now): bool
    {
        $ip = substr(trim($ip), 0, 45);
        if ($ip === '') {
            $ip = '0.0.0.0';
        }
        return (bool) $this->storage()->transaction(function () use ($ip, $now): bool {
            $row   = $this->storage()->selectOne(
                'SELECT window_start, count FROM ' . Schema::ORDER_RATE . ' WHERE ip = :ip',
                ['ip' => $ip],
            );
            $nowTs = strtotime($now) ?: time();
            if ($row === null) {
                $this->storage()->execute(
                    'INSERT INTO ' . Schema::ORDER_RATE . ' (ip, window_start, count) VALUES (:ip, :ws, 1)',
                    ['ip' => $ip, 'ws' => $now],
                );
                return true;
            }
            $windowTs = strtotime((string) $row['window_start']) ?: 0;
            if (($nowTs - $windowTs) >= $this->windowSeconds) {
                $this->storage()->execute(
                    'UPDATE ' . Schema::ORDER_RATE . ' SET window_start = :ws, count = 1 WHERE ip = :ip',
                    ['ws' => $now, 'ip' => $ip],
                );
                return true;
            }
            if ((int) $row['count'] >= $this->limit) {
                return false;
            }
            $this->storage()->execute(
                'UPDATE ' . Schema::ORDER_RATE . ' SET count = count + 1 WHERE ip = :ip',
                ['ip' => $ip],
            );
            return true;
        });
    }

    private function storage(): PluginStorage
    {
        return ($this->storage)();
    }
}
