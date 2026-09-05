<?php

declare(strict_types=1);

namespace DanMat\Restaurant\Tests;

use DanMat\Restaurant\Menu;
use DanMat\Restaurant\Orders;
use DanMat\Restaurant\RestaurantToolset;
use DanMat\Restaurant\Schema;
use DanMat\Restaurant\Tables;
use Nimbus\Api\EntryOpContext;
use Nimbus\Api\TokenPrincipal;
use Nimbus\Auth\Authorizer;
use Nimbus\Database\Connection;
use Nimbus\Mcp\McpError;
use Nimbus\Plugin\PluginStorage;
use PHPUnit\Framework\TestCase;

/**
 * The MCP surface + the authorization matrix. Every restaurant tool is gated on
 * the wildcard-immune `danmat.restaurant` capability — a content `*:write` token
 * can neither see nor call one — reads need `:read`, writes need `:write`, and a
 * denied tool reports as *unknown* (non-enumerating).
 */
final class RestaurantToolsetTest extends TestCase
{
    private RestaurantToolset $toolset;
    private EntryOpContext $ctx;

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

        $storage = new PluginStorage($db);
        $tables  = new Tables(static fn (): PluginStorage => $storage);
        $orders  = new Orders(static fn (): PluginStorage => $storage, $tables, static fn (int $id): ?array => null);
        // The menu reader is never exercised here (order lines are manual), so a
        // reader that would need core content is fine left unbuilt.
        $menu = new Menu(static fn () => throw new \RuntimeException('no content reader in this test'));

        $this->toolset = new RestaurantToolset($tables, $orders, $menu);
        $this->toolset->bindTo('danmat.restaurant');
        $this->ctx = new EntryOpContext('127.0.0.1', '/api/v1/mcp');

        Authorizer::useManagement(['danmat.restaurant']);
    }

    protected function tearDown(): void
    {
        Authorizer::reset();
    }

    private function principal(string ...$scopes): TokenPrincipal
    {
        return new TokenPrincipal(1, 'floor-bot', array_values($scopes));
    }

    public function test_the_tools_are_namespaced_and_split_read_from_write(): void
    {
        $names = array_column($this->toolset->definitions($this->principal('danmat.restaurant:read', 'danmat.restaurant:write')), 'name');
        self::assertSame([
            'restaurant_tables', 'restaurant_table_get', 'restaurant_table_set', 'restaurant_table_status', 'restaurant_table_delete',
            'restaurant_menu', 'restaurant_order_open', 'restaurant_orders', 'restaurant_order_get', 'restaurant_order_status',
            'restaurant_order_add_item', 'restaurant_order_set_item_qty', 'restaurant_order_remove_item', 'restaurant_order_delete',
            'restaurant_kitchen',
        ], $names);
    }

    public function test_a_read_only_token_sees_only_the_read_tools(): void
    {
        $names = array_column($this->toolset->definitions($this->principal('danmat.restaurant:read')), 'name');
        self::assertSame(['restaurant_tables', 'restaurant_table_get', 'restaurant_menu', 'restaurant_orders', 'restaurant_order_get', 'restaurant_kitchen'], $names);
    }

    public function test_the_kitchen_queue_lists_sent_and_preparing_tickets(): void
    {
        $write = $this->principal('danmat.restaurant:read', 'danmat.restaurant:write');
        $tid   = $this->toolset->call('restaurant_table_set', ['label' => '5'], $write, $this->ctx)['table']['id'];
        $oid   = $this->toolset->call('restaurant_order_open', ['table_id' => $tid], $write, $this->ctx)['order']['id'];
        $this->toolset->call('restaurant_order_add_item', ['order_id' => $oid, 'name' => 'Fries', 'price' => '3', 'qty' => 1], $write, $this->ctx);

        // Not in the kitchen while open.
        self::assertSame(0, $this->toolset->call('restaurant_kitchen', [], $write, $this->ctx)['count']);

        $this->toolset->call('restaurant_order_status', ['id' => $oid, 'status' => 'sent'], $write, $this->ctx);
        $queue = $this->toolset->call('restaurant_kitchen', [], $write, $this->ctx);
        self::assertSame(1, $queue['count']);
        self::assertSame('Fries', $queue['tickets'][0]['items'][0]['name']);
    }

    public function test_an_order_can_be_run_end_to_end_over_mcp(): void
    {
        $write = $this->principal('danmat.restaurant:read', 'danmat.restaurant:write');
        $tid   = $this->toolset->call('restaurant_table_set', ['label' => '5'], $write, $this->ctx)['table']['id'];

        $opened = $this->toolset->call('restaurant_order_open', ['table_id' => $tid], $write, $this->ctx);
        self::assertTrue($opened['ok']);
        $orderId = $opened['order']['id'];
        self::assertSame('occupied', $this->toolset->call('restaurant_table_get', ['id' => $tid], $write, $this->ctx)['table']['status']);

        // A manual line (no menu read needed): 2 × 6.00.
        $this->toolset->call('restaurant_order_add_item', ['order_id' => $orderId, 'name' => 'House wine', 'price' => '6', 'qty' => 2], $write, $this->ctx);
        $got = $this->toolset->call('restaurant_order_get', ['id' => $orderId], $write, $this->ctx);
        self::assertSame('12.00', $got['order']['total']);

        $this->toolset->call('restaurant_order_status', ['id' => $orderId, 'status' => 'sent'], $write, $this->ctx);
        self::assertSame('sent', $this->toolset->call('restaurant_order_get', ['id' => $orderId], $write, $this->ctx)['order']['status']);

        self::assertTrue($this->toolset->call('restaurant_order_delete', ['id' => $orderId], $write, $this->ctx)['deleted']);
    }

    public function test_a_content_token_cannot_reach_orders(): void
    {
        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Unknown tool "restaurant_order_open"');
        $this->toolset->call('restaurant_order_open', ['table_id' => 1], $this->principal('*:read', '*:write'), $this->ctx);
    }

    public function test_a_content_token_cannot_reach_the_floor(): void
    {
        self::assertSame([], $this->toolset->definitions($this->principal('*:write', '*:read')));

        $this->expectException(McpError::class);
        $this->expectExceptionMessage('Unknown tool "restaurant_tables"');
        $this->toolset->call('restaurant_tables', [], $this->principal('*:read', '*:write'), $this->ctx);
    }

    public function test_a_read_token_cannot_call_a_write_tool(): void
    {
        $this->expectException(McpError::class);
        $this->toolset->call('restaurant_table_set', ['label' => 'X'], $this->principal('danmat.restaurant:read'), $this->ctx);
    }

    public function test_set_status_get_and_delete_round_trip(): void
    {
        $write = $this->principal('danmat.restaurant:read', 'danmat.restaurant:write');

        $out = $this->toolset->call('restaurant_table_set', ['label' => 'Patio 1', 'seats' => 4], $write, $this->ctx);
        self::assertTrue($out['ok']);
        self::assertSame('open', $out['table']['status']);
        $id = $out['table']['id'];

        $moved = $this->toolset->call('restaurant_table_status', ['id' => $id, 'status' => 'occupied'], $write, $this->ctx);
        self::assertTrue($moved['changed']);
        self::assertSame('occupied', $moved['table']['status']);

        $got = $this->toolset->call('restaurant_table_get', ['id' => $id], $write, $this->ctx);
        self::assertSame('Patio 1', $got['table']['label']);

        $del = $this->toolset->call('restaurant_table_delete', ['id' => $id], $write, $this->ctx);
        self::assertTrue($del['deleted']);
        self::assertNull($this->toolset->call('restaurant_table_get', ['id' => $id], $write, $this->ctx)['table']);
    }

    public function test_a_duplicate_label_comes_back_as_data_not_an_exception(): void
    {
        $write = $this->principal('danmat.restaurant:write');
        $this->toolset->call('restaurant_table_set', ['label' => '5'], $write, $this->ctx);
        $out = $this->toolset->call('restaurant_table_set', ['label' => '5'], $write, $this->ctx);
        self::assertFalse($out['ok']);
        self::assertSame('invalid', $out['error']);
    }

    public function test_a_bad_status_comes_back_as_data(): void
    {
        $write = $this->principal('danmat.restaurant:read', 'danmat.restaurant:write');
        $id    = $this->toolset->call('restaurant_table_set', ['label' => '9'], $write, $this->ctx)['table']['id'];
        $out   = $this->toolset->call('restaurant_table_status', ['id' => $id, 'status' => 'nope'], $write, $this->ctx);
        self::assertFalse($out['ok']);
        self::assertSame('invalid', $out['error']);
    }
}
