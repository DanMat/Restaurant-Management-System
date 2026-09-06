<?php

declare(strict_types=1);

namespace DanMat\Restaurant\Tests;

use DanMat\Restaurant\Orders;
use DanMat\Restaurant\Reports;
use DanMat\Restaurant\Schema;
use DanMat\Restaurant\Tables;
use Nimbus\Database\Connection;
use Nimbus\Plugin\PluginStorage;
use PHPUnit\Framework\TestCase;

/**
 * Reports — revenue aggregation over paid orders. Seeded through the real Orders
 * service (open → add item → pay at a chosen time), so the figures exercise the
 * same amount_paid/paid_at the app actually writes.
 */
final class ReportsTest extends TestCase
{
    private Reports $reports;
    private Orders $orders;
    private Tables $tables;

    protected function setUp(): void
    {
        $db = new Connection([
            'host' => getenv('TEST_DB_HOST') ?: 'db',
            'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
            'name' => getenv('TEST_DB_NAME') ?: 'nimbus_test',
            'user' => getenv('TEST_DB_USER') ?: 'root',
            'pass' => ($p = getenv('TEST_DB_PASS')) !== false ? $p : 'root',
        ]);
        foreach ([...Schema::tables(), ...Schema::orders()] as $sql) {
            $db->execute($sql);
        }
        $db->execute('TRUNCATE ' . Schema::TABLE);
        $db->execute('TRUNCATE ' . Schema::ORDER);
        $db->execute('TRUNCATE ' . Schema::ORDER_ITEM);

        $storage      = new PluginStorage($db);
        $this->tables = new Tables(static fn (): PluginStorage => $storage);
        $this->orders = new Orders(static fn (): PluginStorage => $storage, $this->tables, static fn (int $id): ?array => null);
        $this->reports = new Reports(static fn (): PluginStorage => $storage);
    }

    /** Open a fresh order (own table), add one manual line, and pay it at $paidAt. */
    private function paidOrder(string $label, string $item, string $price, int $qty, string $paidAt): void
    {
        $t  = $this->tables->save(null, ['label' => $label], $paidAt);
        $id = $this->orders->open($t, $paidAt);
        $this->orders->addItem($id, null, $item, $price, $qty, $paidAt);
        $this->orders->pay($id, 'card', $paidAt);
    }

    public function test_revenue_between_sums_paid_orders_in_the_window(): void
    {
        $this->paidOrder('1', 'Steak', '20', 2, '2026-06-01 12:00:00'); // 40 today
        $this->paidOrder('2', 'Soup', '5', 1, '2026-06-01 19:00:00');   // 5 today
        $this->paidOrder('3', 'Wine', '8', 1, '2026-05-31 20:00:00');   // 8 yesterday

        $today = $this->reports->revenueBetween('2026-06-01 00:00:00', '2026-06-02 00:00:00');
        self::assertSame('45.00', $today['revenue']);
        self::assertSame(2, $today['orders']);

        $week = $this->reports->revenueBetween('2026-05-26 00:00:00', '2026-06-02 00:00:00');
        self::assertSame('53.00', $week['revenue'], 'includes yesterday');
        self::assertSame(3, $week['orders']);
    }

    public function test_an_unpaid_order_is_not_revenue_but_is_active(): void
    {
        $t = $this->tables->save(null, ['label' => '9'], '2026-06-01 12:00:00');
        $this->orders->open($t, '2026-06-01 12:00:00'); // open, unpaid

        self::assertSame('0.00', $this->reports->revenueBetween('2026-06-01 00:00:00', '2026-06-02 00:00:00')['revenue']);
        self::assertSame(1, $this->reports->activeOrders(), 'the open order is active');

        $this->paidOrder('10', 'X', '5', 1, '2026-06-01 13:00:00');
        self::assertSame(1, $this->reports->activeOrders(), 'a paid (closed) order is not active');
    }

    public function test_revenue_by_day_groups_and_orders(): void
    {
        $this->paidOrder('1', 'A', '10', 1, '2026-06-01 12:00:00');
        $this->paidOrder('2', 'B', '10', 1, '2026-06-02 12:00:00');
        $this->paidOrder('3', 'C', '5', 1, '2026-06-02 18:00:00');

        $byDay = $this->reports->revenueByDay('2026-06-01 00:00:00', '2026-06-03 00:00:00');
        self::assertSame('2026-06-01', $byDay[0]['day']);
        self::assertSame('10.00', $byDay[0]['revenue']);
        self::assertSame('2026-06-02', $byDay[1]['day']);
        self::assertSame('15.00', $byDay[1]['revenue']);
        self::assertSame(2, $byDay[1]['orders']);
    }

    public function test_top_items_ranks_by_quantity(): void
    {
        $this->paidOrder('1', 'Fries', '4', 5, '2026-06-01 12:00:00');
        $this->paidOrder('2', 'Steak', '20', 2, '2026-06-01 13:00:00');
        $this->paidOrder('3', 'Fries', '4', 1, '2026-06-01 14:00:00');

        $top = $this->reports->topItems('2026-06-01 00:00:00', '2026-06-02 00:00:00', 5);
        self::assertSame('Fries', $top[0]['name']);
        self::assertSame(6, $top[0]['qty']);
        self::assertSame('24.00', $top[0]['revenue']);
        self::assertSame('Steak', $top[1]['name']);
    }
}
