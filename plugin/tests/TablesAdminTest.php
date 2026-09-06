<?php

declare(strict_types=1);

namespace DanMat\Restaurant\Tests;

use DanMat\Restaurant\Schema;
use DanMat\Restaurant\Tables;
use DanMat\Restaurant\TablesAdmin;
use Nimbus\Database\Connection;
use Nimbus\Plugin\PluginStorage;
use PHPUnit\Framework\TestCase;

/**
 * The floor board renders author input (table labels) to staff — the real risk is
 * an un-escaped value (stored XSS). This proves labels are escaped, that the board
 * shows tables by status with the right quick actions, and that the CSRF token
 * reaches every form.
 */
final class TablesAdminTest extends TestCase
{
    private Tables $tables;
    private TablesAdmin $admin;

    protected function setUp(): void
    {
        $db = new Connection([
            'host' => getenv('TEST_DB_HOST') ?: 'db',
            'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
            'name' => getenv('TEST_DB_NAME') ?: 'nimbus_test',
            'user' => getenv('TEST_DB_USER') ?: 'root',
            'pass' => ($p = getenv('TEST_DB_PASS')) !== false ? $p : 'root',
        ]);
        foreach (Schema::tables() as $sql) {
            $db->execute($sql);
        }
        $db->execute('TRUNCATE ' . Schema::TABLE);

        $storage      = new PluginStorage($db);
        $this->tables = new Tables(static fn (): PluginStorage => $storage);
        $this->admin  = new TablesAdmin($this->tables);
    }

    public function test_the_board_escapes_a_hostile_label(): void
    {
        $this->tables->save(null, ['label' => '<script>alert(1)</script>'], '2026-01-01 09:00:00');

        $html = $this->admin->render('CSRF123', null, null, null, 'n');

        self::assertStringNotContainsString('<script>alert(1)</script>', $html, 'the label is escaped');
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('value="CSRF123"', $html, 'the CSRF token is in the forms');
    }

    public function test_the_board_shows_status_and_quick_actions(): void
    {
        $this->tables->save(null, ['label' => '1', 'status' => 'occupied'], '2026-01-01 09:00:00');

        $html = $this->admin->render('CSRF123', null, null, null, 'n');

        self::assertStringContainsString('rz-status-occupied', $html, 'the token is marked with its status');
        self::assertStringContainsString('rz-circle', $html, 'tables render as circular tokens (the RAS signature)');
        self::assertStringContainsString('action="/admin/restaurant/table-status"', $html, 'quick-action posts to the status action');
        // An occupied table offers "Clear" (→ dirty), not "Seat".
        self::assertStringContainsString('value="dirty"', $html);
    }

    public function test_the_edit_form_loads_and_escapes_the_table(): void
    {
        $id = $this->tables->save(null, ['label' => '"><b>7</b>', 'seats' => '8'], '2026-01-01 09:00:00');

        $html = $this->admin->render('CSRF123', null, (string) $id, null, 'n');

        self::assertStringContainsString('Edit table', $html);
        self::assertStringContainsString('value="8"', $html, 'seats loaded into the form');
        self::assertStringNotContainsString('<b>7</b>', $html, 'the loaded label is escaped');
    }

    public function test_an_empty_floor_prompts_to_add(): void
    {
        self::assertStringContainsString('No tables yet', $this->admin->render('CSRF123', null, null, null, 'n'));
    }
}
