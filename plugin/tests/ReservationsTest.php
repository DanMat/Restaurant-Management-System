<?php

declare(strict_types=1);

namespace DanMat\Restaurant\Tests;

use DanMat\Restaurant\Reservations;
use DanMat\Restaurant\Schema;
use DanMat\Restaurant\Tables;
use Nimbus\Database\Connection;
use Nimbus\Plugin\PluginStorage;
use PHPUnit\Framework\TestCase;

/**
 * The Reservations service. The load-bearing property is the CRM boundary:
 * `contact_id` is stored as a bare link and never resolved (no CRM read), while the
 * booking's own fields are validated normally. Plus the usual discipline: party name
 * required, allow-listed status, a validated same-plugin table ref, strict datetime.
 */
final class ReservationsTest extends TestCase
{
    private Reservations $reservations;
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
        foreach ([...Schema::tables(), ...Schema::reservations()] as $sql) {
            $db->execute($sql);
        }
        $db->execute('TRUNCATE ' . Schema::TABLE);
        $db->execute('TRUNCATE ' . Schema::RESERVATION);

        $storage            = new PluginStorage($db);
        $this->tables       = new Tables(static fn (): PluginStorage => $storage);
        $this->reservations = new Reservations(static fn (): PluginStorage => $storage, $this->tables);
    }

    private const NOW = '2026-01-01 09:00:00';

    public function test_create_get_and_update_round_trip(): void
    {
        $id = $this->reservations->save(null, ['party_name' => 'Smith', 'party_size' => '4', 'reserved_at' => '2026-06-01 19:30:00'], self::NOW);
        $r  = $this->reservations->get($id);
        self::assertNotNull($r);
        self::assertSame('Smith', $r['party_name']);
        self::assertSame(4, $r['party_size']);
        self::assertSame('2026-06-01 19:30:00', $r['reserved_at']);
        self::assertSame('booked', $r['status']);

        $this->reservations->save($id, ['party_size' => '6'], '2026-01-02 09:00:00');
        self::assertSame(6, $this->reservations->get($id)['party_size']);
        self::assertSame('Smith', $this->reservations->get($id)['party_name'], 'unsent fields unchanged');
    }

    public function test_a_reservation_needs_a_party_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->reservations->save(null, ['party_size' => '2'], self::NOW);
    }

    public function test_reserved_at_accepts_datetime_local_and_rejects_junk(): void
    {
        $id = $this->reservations->save(null, ['party_name' => 'A', 'reserved_at' => '2026-06-01T19:30'], self::NOW);
        self::assertSame('2026-06-01 19:30:00', $this->reservations->get($id)['reserved_at']);

        $this->expectException(\InvalidArgumentException::class);
        $this->reservations->save(null, ['party_name' => 'B', 'reserved_at' => 'friday-ish'], self::NOW);
    }

    public function test_a_table_link_must_exist(): void
    {
        $tableId = $this->tables->save(null, ['label' => '7'], self::NOW);
        $id      = $this->reservations->save(null, ['party_name' => 'Jones', 'table_id' => (string) $tableId], self::NOW);
        self::assertSame($tableId, $this->reservations->get($id)['table_id']);
        self::assertSame('7', $this->reservations->get($id)['table_label']);

        $this->expectException(\InvalidArgumentException::class);
        $this->reservations->save(null, ['party_name' => 'Ghost', 'table_id' => '99999'], self::NOW);
    }

    public function test_contact_id_is_stored_as_a_bare_link_not_resolved(): void
    {
        // A positive int is accepted and stored verbatim — no CRM read, no existence
        // check (that would breach the CRM's gate). A non-positive value is rejected.
        $id = $this->reservations->save(null, ['party_name' => 'Regular', 'contact_id' => '4242'], self::NOW);
        self::assertSame(4242, $this->reservations->get($id)['contact_id']);

        $blank = $this->reservations->save(null, ['party_name' => 'Walkin', 'contact_id' => ''], self::NOW);
        self::assertNull($this->reservations->get($blank)['contact_id']);
    }

    public function test_a_bad_contact_id_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->reservations->save(null, ['party_name' => 'X', 'contact_id' => '-1'], self::NOW);
    }

    public function test_status_is_an_allow_list_and_set_status_moves_it(): void
    {
        $id = $this->reservations->save(null, ['party_name' => 'X'], self::NOW);
        self::assertSame(1, $this->reservations->setStatus($id, 'seated', self::NOW));
        self::assertSame('seated', $this->reservations->get($id)['status']);

        $this->expectException(\InvalidArgumentException::class);
        $this->reservations->setStatus($id, 'teleported', self::NOW);
    }

    public function test_all_orders_soonest_first_and_filters_by_status(): void
    {
        $this->reservations->save(null, ['party_name' => 'Late', 'reserved_at' => '2026-06-01 21:00:00'], self::NOW);
        $this->reservations->save(null, ['party_name' => 'Early', 'reserved_at' => '2026-06-01 18:00:00'], self::NOW);
        $cancel = $this->reservations->save(null, ['party_name' => 'Gone', 'reserved_at' => '2026-06-01 19:00:00', 'status' => 'cancelled'], self::NOW);

        $names = array_column($this->reservations->all(), 'party_name');
        self::assertSame(['Early', 'Gone', 'Late'], $names, 'soonest first');
        self::assertCount(1, $this->reservations->all('cancelled'));
        self::assertSame($cancel, $this->reservations->all('cancelled')[0]['id']);
    }

    public function test_delete_removes_it(): void
    {
        $id = $this->reservations->save(null, ['party_name' => 'X'], self::NOW);
        self::assertSame(1, $this->reservations->delete($id));
        self::assertNull($this->reservations->get($id));
    }
}
