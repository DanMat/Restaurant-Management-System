<?php

declare(strict_types=1);

namespace DanMat\Restaurant\Tests;

use DanMat\Restaurant\Reservations;
use DanMat\Restaurant\ReservationsAdmin;
use DanMat\Restaurant\Schema;
use DanMat\Restaurant\Tables;
use Nimbus\Database\Connection;
use Nimbus\Plugin\PluginStorage;
use PHPUnit\Framework\TestCase;

/**
 * The reservation book renders the party name (author input) and must escape it,
 * and it links a CRM-linked booking out to the CRM page (never displaying CRM data
 * itself). A booking without a linked guest shows no CRM link.
 */
final class ReservationsAdminTest extends TestCase
{
    private Reservations $reservations;
    private ReservationsAdmin $admin;

    protected function setUp(): void
    {
        $db = new Connection([
            'host' => getenv('TEST_DB_HOST') ?: 'db',
            'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
            'name' => getenv('TEST_DB_NAME') ?: 'nimbus_test',
            'user' => getenv('TEST_DB_USER') ?: 'root',
            'pass' => ($p = getenv('TEST_DB_PASS')) !== false ? $p : 'root',
        ]);
        foreach ([...Schema::tables(), ...Schema::reservations()] as $sql) {
            $db->execute($sql);
        }
        $db->execute('TRUNCATE ' . Schema::TABLE);
        $db->execute('TRUNCATE ' . Schema::RESERVATION);

        $storage            = new PluginStorage($db);
        $tables             = new Tables(static fn (): PluginStorage => $storage);
        $this->reservations = new Reservations(static fn (): PluginStorage => $storage, $tables);
        $this->admin        = new ReservationsAdmin($this->reservations, $tables);
    }

    public function test_it_escapes_a_hostile_party_name(): void
    {
        $this->reservations->save(null, ['party_name' => '<script>alert(1)</script>'], '2026-01-01 09:00:00');

        $html = $this->admin->render('CSRF123', null, null, null, 'n');

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('value="CSRF123"', $html);
    }

    public function test_a_linked_booking_links_out_to_the_crm_but_shows_no_crm_data(): void
    {
        $this->reservations->save(null, ['party_name' => 'Regular', 'contact_id' => '4242'], '2026-01-01 09:00:00');

        $html = $this->admin->render('CSRF123', null, null, null, 'n');
        // Links to the CRM's own (separately gated) contact page — no contact data here.
        self::assertStringContainsString('href="/admin/crm?edit=4242"', $html);
        self::assertStringContainsString('Guest in CRM', $html);
    }

    public function test_an_unlinked_booking_shows_no_crm_link(): void
    {
        $this->reservations->save(null, ['party_name' => 'Walk-in'], '2026-01-01 09:00:00');

        $html = $this->admin->render('CSRF123', null, null, null, 'n');
        self::assertStringNotContainsString('/admin/crm?edit=', $html);
    }
}
