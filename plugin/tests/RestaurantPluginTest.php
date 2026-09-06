<?php

declare(strict_types=1);

namespace DanMat\Restaurant\Tests;

use DanMat\Restaurant\RestaurantPlugin;
use Nimbus\Plugin\PluginCapabilities;
use Nimbus\Plugin\PluginContext;
use PHPUnit\Framework\TestCase;

/**
 * The plugin's registration wiring (ADR 0030 / Slice 5): it declares its
 * fine-grained staff actions, and gates each terminal on the right one — floor
 * staff reach the floor and orders, cooks the kitchen. register() touches no
 * database (storage and content are resolved lazily), so this is a pure unit test.
 */
final class RestaurantPluginTest extends TestCase
{
    private PluginCapabilities $caps;

    protected function setUp(): void
    {
        $this->caps = new PluginCapabilities();
        (new RestaurantPlugin())->register(new PluginContext($this->caps, RestaurantPlugin::ID));
    }

    public function test_it_declares_the_fine_grained_staff_actions_as_grants(): void
    {
        $grantable = $this->caps->capabilities->grantable();

        self::assertArrayHasKey('danmat.restaurant:floor', $grantable);
        self::assertArrayHasKey('danmat.restaurant:kitchen', $grantable);
        self::assertArrayHasKey('danmat.restaurant:manage', $grantable);
        // read/write remain for the MCP/agent surface.
        self::assertArrayHasKey('danmat.restaurant:read', $grantable);
        self::assertArrayHasKey('danmat.restaurant:write', $grantable);
        self::assertSame('Restaurant: kitchen', $grantable['danmat.restaurant:kitchen'], 'a finer action labels as itself');
    }

    public function test_each_terminal_is_gated_on_the_right_action(): void
    {
        $gate = [];
        foreach ($this->caps->adminPages->all() as $page) {
            $gate[$page['slug']] = $page['capability'];
        }

        self::assertSame('danmat.restaurant:floor', $gate['restaurant'], 'the floor is floor-staff only');
        self::assertSame('danmat.restaurant:floor', $gate['restaurant-orders'], 'orders + payment are floor-staff');
        self::assertSame('danmat.restaurant:kitchen', $gate['restaurant-kitchen'], 'the kitchen is cooks only');
        self::assertSame('danmat.restaurant:floor', $gate['restaurant-reservations'], 'the book is floor-staff');
    }
}
