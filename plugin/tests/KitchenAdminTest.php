<?php

declare(strict_types=1);

namespace DanMat\Restaurant\Tests;

use DanMat\Restaurant\KitchenAdmin;
use DanMat\Restaurant\Orders;
use DanMat\Restaurant\Schema;
use DanMat\Restaurant\Tables;
use Nimbus\Database\Connection;
use Nimbus\Plugin\PluginStorage;
use PHPUnit\Framework\TestCase;

/**
 * The kitchen display renders order items (author input) to the cook and must
 * escape them; it buckets tickets into New / Preparing / Ready with the right
 * advance action, and carries the CSRF token + a nonce'd auto-refresh.
 */
final class KitchenAdminTest extends TestCase
{
    private Orders $orders;
    private Tables $tables;
    private KitchenAdmin $admin;

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
        $this->admin  = new KitchenAdmin($this->orders);
    }

    private function sentOrderWithItem(string $itemName): int
    {
        $t  = $this->tables->save(null, ['label' => '1'], '2026-01-01 12:00:00');
        $id = $this->orders->open($t, '2026-01-01 12:00:00');
        $this->orders->addItem($id, null, $itemName, '5', 1, '2026-01-01 12:00:00');
        $this->orders->setStatus($id, 'sent', '2026-01-01 12:00:00');
        return $id;
    }

    public function test_a_new_ticket_shows_in_the_new_column_with_a_start_action(): void
    {
        $this->sentOrderWithItem('Chowder');

        $html = $this->admin->render('CSRF123', null, 'n');

        self::assertStringContainsString('Chowder', $html);
        self::assertStringContainsString('action="/admin/restaurant-kitchen/advance"', $html);
        self::assertStringContainsString('value="preparing"', $html, 'a New ticket advances to preparing');
        self::assertStringContainsString('Start', $html);
        self::assertStringContainsString('value="CSRF123"', $html);
    }

    public function test_it_escapes_a_hostile_item_name(): void
    {
        $this->sentOrderWithItem('<script>alert(1)</script>');

        $html = $this->admin->render('CSRF123', null, 'n');

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_a_ready_ticket_has_no_further_kitchen_action(): void
    {
        $id = $this->sentOrderWithItem('Soup');
        $this->orders->setStatus($id, 'ready', '2026-01-01 12:05:00');

        $html = $this->admin->render('CSRF123', null, 'n');
        // The Ready column shows it, but offers no advance (the floor serves it).
        self::assertStringContainsString('Ready', $html);
        self::assertStringNotContainsString('value="served"', $html, 'the kitchen never serves; the floor does');
    }

    public function test_the_page_auto_refreshes_with_a_nonced_script(): void
    {
        self::assertStringContainsString('<script nonce="the-nonce">', $this->admin->render('CSRF123', null, 'the-nonce'));
    }
}
