<?php

declare(strict_types=1);

namespace DanMat\Restaurant\Tests;

use DanMat\Restaurant\MenuSource;
use DanMat\Restaurant\Orders;
use DanMat\Restaurant\OrdersAdmin;
use DanMat\Restaurant\Schema;
use DanMat\Restaurant\Tables;
use Nimbus\Database\Connection;
use Nimbus\Plugin\PluginStorage;
use PHPUnit\Framework\TestCase;

/**
 * The orders terminal renders author input (manual line names, menu labels) to
 * staff, and must escape it; the total it shows is the server-computed one. A fake
 * {@see MenuSource} supplies a canned picker, so this needs no menu collection.
 */
final class OrdersAdminTest extends TestCase
{
    private Orders $orders;
    private Tables $tables;
    private OrdersAdmin $admin;

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

        $menu = new class () implements MenuSource {
            public function items(): array
            {
                return [['id' => 101, 'name' => 'Margherita', 'price' => '12.50', 'category' => 'Mains']];
            }
        };
        $this->admin = new OrdersAdmin($this->orders, $this->tables, $menu);
    }

    private function openOrder(): int
    {
        $t = $this->tables->save(null, ['label' => '1'], '2026-01-01 12:00:00');
        return $this->orders->open($t, '2026-01-01 12:00:00');
    }

    public function test_the_list_shows_the_open_form_and_a_table_option(): void
    {
        $this->tables->save(null, ['label' => 'Patio 2'], '2026-01-01 12:00:00');

        $html = $this->admin->render('CSRF123', null, null, null, 'n');

        self::assertStringContainsString('Open an order', $html);
        self::assertStringContainsString('action="/admin/restaurant-orders/order-open"', $html);
        self::assertStringContainsString('Patio 2', $html, 'the table is offered');
        self::assertStringContainsString('value="CSRF123"', $html);
    }

    public function test_the_order_screen_escapes_a_manual_line_and_shows_the_total(): void
    {
        $id = $this->openOrder();
        $this->orders->addItem($id, null, '<b>Corkage</b>', '5', 2, '2026-01-01 12:00:00');

        $html = $this->admin->render('CSRF123', null, (string) $id, null, 'n');

        self::assertStringContainsString('Order #' . $id, $html);
        self::assertStringNotContainsString('<b>Corkage</b>', $html, 'a hostile line name is escaped');
        self::assertStringContainsString('&lt;b&gt;Corkage&lt;/b&gt;', $html);
        self::assertStringContainsString('10.00', $html, 'the computed total shows');
        self::assertStringContainsString('Margherita', $html, 'the menu picker is populated');
        self::assertStringContainsString('Send to kitchen', $html, 'an open order can be sent to the kitchen');
    }

    public function test_a_sent_order_offers_mark_served(): void
    {
        $id = $this->openOrder();
        $this->orders->setStatus($id, 'sent', '2026-01-01 12:00:00');

        $html = $this->admin->render('CSRF123', null, (string) $id, null, 'n');
        self::assertStringContainsString('Mark served', $html);
        self::assertStringNotContainsString('Send to kitchen', $html);
    }
}
