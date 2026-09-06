<?php

declare(strict_types=1);

namespace DanMat\Restaurant\Tests;

use DanMat\Restaurant\RateLimiter;
use DanMat\Restaurant\Schema;
use Nimbus\Database\Connection;
use Nimbus\Plugin\PluginStorage;
use PHPUnit\Framework\TestCase;

/**
 * The per-IP fixed-window throttle guarding the public online-order endpoint:
 * allow up to the limit within a window, refuse beyond it, and reset once the
 * window expires. Backed by the plugin's own {@see Schema::ORDER_RATE} table.
 */
final class RateLimiterTest extends TestCase
{
    private RateLimiter $rate;

    protected function setUp(): void
    {
        $db = new Connection([
            'host' => getenv('TEST_DB_HOST') ?: 'db',
            'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
            'name' => getenv('TEST_DB_NAME') ?: 'nimbus_test',
            'user' => getenv('TEST_DB_USER') ?: 'root',
            'pass' => ($p = getenv('TEST_DB_PASS')) !== false ? $p : 'root',
        ]);
        foreach (Schema::orders() as $sql) {
            $db->execute($sql);
        }
        $db->execute('TRUNCATE ' . Schema::ORDER_RATE);

        $storage    = new PluginStorage($db);
        $this->rate = new RateLimiter(static fn (): PluginStorage => $storage, 2, 60);
    }

    public function test_it_allows_up_to_the_limit_then_refuses(): void
    {
        $now = '2026-01-01 12:00:00';
        self::assertTrue($this->rate->allow('1.2.3.4', $now), 'first is allowed');
        self::assertTrue($this->rate->allow('1.2.3.4', $now), 'second is allowed');
        self::assertFalse($this->rate->allow('1.2.3.4', $now), 'third within the window is refused');
    }

    public function test_a_different_ip_has_its_own_budget(): void
    {
        $now = '2026-01-01 12:00:00';
        $this->rate->allow('1.2.3.4', $now);
        $this->rate->allow('1.2.3.4', $now);
        self::assertFalse($this->rate->allow('1.2.3.4', $now));
        self::assertTrue($this->rate->allow('5.6.7.8', $now), 'a different IP is not throttled');
    }

    public function test_the_window_resets_after_it_expires(): void
    {
        self::assertTrue($this->rate->allow('1.2.3.4', '2026-01-01 12:00:00'));
        self::assertTrue($this->rate->allow('1.2.3.4', '2026-01-01 12:00:00'));
        self::assertFalse($this->rate->allow('1.2.3.4', '2026-01-01 12:00:30'), 'still within the window');
        self::assertTrue($this->rate->allow('1.2.3.4', '2026-01-01 12:01:05'), 'a new window allows again');
    }
}
