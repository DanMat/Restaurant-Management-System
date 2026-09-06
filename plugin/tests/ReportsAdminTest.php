<?php

declare(strict_types=1);

namespace DanMat\Restaurant\Tests;

use DanMat\Restaurant\Orders;
use DanMat\Restaurant\Reports;
use DanMat\Restaurant\ReportsAdmin;
use DanMat\Restaurant\Schema;
use DanMat\Restaurant\Tables;
use Nimbus\Database\Connection;
use Nimbus\Plugin\PluginStorage;
use PHPUnit\Framework\TestCase;

/**
 * The manager dashboard renders aggregated figures (read-only) and item names
 * (author input), which must be escaped. A fixed reference time makes the windows
 * deterministic.
 */
final class ReportsAdminTest extends TestCase
{
    private Orders $orders;
    private Tables $tables;
    private ReportsAdmin $admin;

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
        $this->admin  = new ReportsAdmin(new Reports(static fn (): PluginStorage => $storage));
    }

    private function paidOrder(string $label, string $item, string $price, int $qty, string $paidAt): void
    {
        $t  = $this->tables->save(null, ['label' => $label], $paidAt);
        $id = $this->orders->open($t, $paidAt);
        $this->orders->addItem($id, null, $item, $price, $qty, $paidAt);
        $this->orders->pay($id, 'card', $paidAt);
    }

    public function test_it_shows_todays_revenue_and_escapes_item_names(): void
    {
        $this->paidOrder('1', '<b>Special</b>', '10', 2, '2026-06-01 12:00:00');

        $html = $this->admin->render('', null, 'n', '2026-06-01 20:00:00');

        self::assertStringContainsString('Revenue today', $html);
        self::assertStringContainsString('20.00', $html, 'the settled revenue shows');
        self::assertStringNotContainsString('<b>Special</b>', $html, 'a hostile item name is escaped');
        self::assertStringContainsString('&lt;b&gt;Special&lt;/b&gt;', $html);
    }

    public function test_an_empty_period_reads_cleanly(): void
    {
        $html = $this->admin->render('', null, 'n', '2026-06-01 20:00:00');
        self::assertStringContainsString('0.00', $html);
        self::assertStringContainsString('No paid orders in the last 7 days.', $html);
    }
}
