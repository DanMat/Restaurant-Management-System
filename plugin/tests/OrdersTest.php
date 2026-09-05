<?php

declare(strict_types=1);

namespace DanMat\Restaurant\Tests;

use DanMat\Restaurant\Orders;
use DanMat\Restaurant\Schema;
use DanMat\Restaurant\Tables;
use Nimbus\Database\Connection;
use Nimbus\Plugin\PluginStorage;
use PHPUnit\Framework\TestCase;

/**
 * The Orders service — the heart of service. Opening occupies the table; lines
 * snapshot name + price at order time; the total is always **computed** from the
 * lines (never stored/trusted); the workflow status is an allow-list; a delete takes
 * the lines with it. The menu lives in a collection the service never reads directly
 * — it takes a snapshot resolver, faked here — so this needs no core schema.
 */
final class OrdersTest extends TestCase
{
    private Orders $orders;
    private Tables $tables;

    /** A fake menu: id => [name, price]. */
    private const MENU = [
        101 => ['name' => 'Margherita', 'price' => '12.50'],
        102 => ['name' => 'Miso Soup', 'price' => '3.50'],
    ];

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
        $this->orders = new Orders(
            static fn (): PluginStorage => $storage,
            $this->tables,
            static fn (int $id): ?array => self::MENU[$id] ?? null,
        );
    }

    private const NOW = '2026-01-01 12:00:00';

    private function table(string $label = '1'): int
    {
        return $this->tables->save(null, ['label' => $label], self::NOW);
    }

    public function test_opening_an_order_occupies_the_table(): void
    {
        $t  = $this->table();
        $id = $this->orders->open($t, self::NOW);

        $order = $this->orders->get($id);
        self::assertNotNull($order);
        self::assertSame('open', $order['status']);
        self::assertSame($t, $order['table_id']);
        self::assertSame('occupied', $this->tables->get($t)['status'], 'seating a party occupies the table');
    }

    public function test_opening_on_a_missing_table_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->orders->open(999999, self::NOW);
    }

    public function test_adding_a_menu_item_snapshots_name_and_price_and_totals(): void
    {
        $id = $this->orders->open($this->table(), self::NOW);
        $this->orders->addItem($id, 101, null, null, 2, self::NOW); // 2 × 12.50
        $this->orders->addItem($id, 102, null, null, 1, self::NOW); // 1 × 3.50

        $order = $this->orders->get($id);
        self::assertCount(2, $order['items']);
        self::assertSame('Margherita', $order['items'][0]['name']);
        self::assertSame('12.50', $order['items'][0]['unit_price']);
        self::assertSame('25.00', $order['items'][0]['line_total']);
        self::assertSame('28.50', $order['total'], 'the total is computed from the lines');
    }

    public function test_a_manual_line_is_allowed_but_a_bad_price_is_rejected(): void
    {
        $id = $this->orders->open($this->table(), self::NOW);
        $this->orders->addItem($id, null, 'Corkage', '5', 1, self::NOW);
        self::assertSame('5.00', $this->orders->get($id)['items'][0]['unit_price']);

        $this->expectException(\InvalidArgumentException::class);
        $this->orders->addItem($id, null, 'Bad', 'free', 1, self::NOW);
    }

    public function test_adding_an_unknown_menu_item_is_rejected(): void
    {
        $id = $this->orders->open($this->table(), self::NOW);
        $this->expectException(\InvalidArgumentException::class);
        $this->orders->addItem($id, 999, null, null, 1, self::NOW);
    }

    public function test_adding_to_a_missing_order_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->orders->addItem(424242, 101, null, null, 1, self::NOW);
    }

    public function test_a_bad_quantity_is_rejected(): void
    {
        $id = $this->orders->open($this->table(), self::NOW);
        $this->expectException(\InvalidArgumentException::class);
        $this->orders->addItem($id, 101, null, null, 0, self::NOW);
    }

    public function test_set_qty_changes_and_zero_removes(): void
    {
        $id     = $this->orders->open($this->table(), self::NOW);
        $this->orders->addItem($id, 101, null, null, 1, self::NOW);
        $itemId = $this->orders->get($id)['items'][0]['id'];

        $this->orders->setItemQty($itemId, 3, self::NOW);
        self::assertSame('37.50', $this->orders->get($id)['total']);

        $this->orders->setItemQty($itemId, 0, self::NOW);
        self::assertSame([], $this->orders->get($id)['items'], 'qty 0 removes the line');
    }

    public function test_remove_item(): void
    {
        $id     = $this->orders->open($this->table(), self::NOW);
        $this->orders->addItem($id, 102, null, null, 1, self::NOW);
        $itemId = $this->orders->get($id)['items'][0]['id'];

        self::assertSame(1, $this->orders->removeItem($itemId));
        self::assertSame('0.00', $this->orders->get($id)['total']);
    }

    public function test_status_workflow_is_an_allow_list(): void
    {
        $id = $this->orders->open($this->table(), self::NOW);
        self::assertSame(1, $this->orders->setStatus($id, 'sent', self::NOW));
        self::assertSame('sent', $this->orders->get($id)['status']);

        $this->expectException(\InvalidArgumentException::class);
        $this->orders->setStatus($id, 'incinerated', self::NOW);
    }

    public function test_delete_takes_the_line_items_with_it(): void
    {
        $id = $this->orders->open($this->table(), self::NOW);
        $this->orders->addItem($id, 101, null, null, 1, self::NOW);

        self::assertSame(1, $this->orders->delete($id));
        self::assertNull($this->orders->get($id));
    }

    public function test_tickets_by_status_returns_the_kitchen_queue_oldest_first(): void
    {
        // Two orders in the kitchen (sent, preparing), one still open, one served.
        $a = $this->orders->open($this->table('1'), '2026-01-01 12:00:00');
        $this->orders->addItem($a, 101, null, null, 2, '2026-01-01 12:00:00');
        $this->orders->setStatus($a, 'sent', '2026-01-01 12:01:00');

        $b = $this->orders->open($this->table('2'), '2026-01-01 12:05:00');
        $this->orders->setStatus($b, 'preparing', '2026-01-01 12:06:00');

        $open   = $this->orders->open($this->table('3'), '2026-01-01 12:10:00');
        $served = $this->orders->open($this->table('4'), '2026-01-01 12:11:00');
        $this->orders->setStatus($served, 'served', '2026-01-01 12:12:00');

        $tickets = $this->orders->ticketsByStatus(['sent', 'preparing', 'ready']);

        self::assertCount(2, $tickets, 'only kitchen-state orders, not open or served');
        self::assertSame($a, $tickets[0]['id'], 'oldest ticket first (FIFO)');
        self::assertSame($b, $tickets[1]['id']);
        self::assertSame('Margherita', $tickets[0]['items'][0]['name']);
        self::assertSame(2, $tickets[0]['items'][0]['qty']);
        self::assertNotContains($open, array_column($tickets, 'id'));
    }

    public function test_tickets_by_status_ignores_unknown_statuses(): void
    {
        self::assertSame([], $this->orders->ticketsByStatus(['nonsense']));
    }

    public function test_all_filters_by_status_and_table(): void
    {
        $t1 = $this->table('1');
        $t2 = $this->table('2');
        $o1 = $this->orders->open($t1, self::NOW);
        $this->orders->open($t2, self::NOW);
        $this->orders->setStatus($o1, 'served', self::NOW);

        self::assertCount(2, $this->orders->all());
        self::assertCount(1, $this->orders->all('served'));
        self::assertCount(1, $this->orders->all(null, $t2));
    }
}
