<?php

declare(strict_types=1);

namespace DanMat\Restaurant\Tests;

use DanMat\Restaurant\Schema;
use DanMat\Restaurant\Tables;
use Nimbus\Database\Connection;
use Nimbus\Plugin\PluginStorage;
use PHPUnit\Framework\TestCase;

/**
 * The Tables service — the write discipline the review pinned: a field allow-list
 * (no over-posting), a required + unique label, bounded seats, a status that is a
 * write-time allow-list, bound SQL, and a total delete.
 */
final class TablesTest extends TestCase
{
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
        foreach (Schema::tables() as $sql) {
            $db->execute($sql);
        }
        $db->execute('TRUNCATE ' . Schema::TABLE);

        $storage      = new PluginStorage($db);
        $this->tables = new Tables(static fn (): PluginStorage => $storage);
    }

    private const NOW = '2026-01-01 09:00:00';

    public function test_create_defaults_get_and_update_round_trip(): void
    {
        $id = $this->tables->save(null, ['label' => 'Patio 1'], self::NOW);
        $t  = $this->tables->get($id);
        self::assertNotNull($t);
        self::assertSame('Patio 1', $t['label']);
        self::assertSame(2, $t['seats'], 'seats defaults to 2');
        self::assertSame('open', $t['status'], 'status defaults to open');

        $this->tables->save($id, ['seats' => '4'], '2026-01-02 09:00:00');
        $t = $this->tables->get($id);
        self::assertSame(4, $t['seats']);
        self::assertSame('Patio 1', $t['label'], 'unsent fields are unchanged');
    }

    public function test_a_table_needs_a_label(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->tables->save(null, ['seats' => '4'], self::NOW);
    }

    public function test_labels_are_unique(): void
    {
        $this->tables->save(null, ['label' => '12'], self::NOW);
        $this->expectException(\InvalidArgumentException::class);
        $this->tables->save(null, ['label' => '12'], self::NOW);
    }

    public function test_a_table_can_be_renamed_to_its_own_label(): void
    {
        $id = $this->tables->save(null, ['label' => '7'], self::NOW);
        // Updating without changing the label must not trip the uniqueness check.
        $this->tables->save($id, ['label' => '7', 'seats' => '6'], self::NOW);
        self::assertSame(6, $this->tables->get($id)['seats']);
    }

    public function test_seats_must_be_a_sane_number(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->tables->save(null, ['label' => 'X', 'seats' => '-3'], self::NOW);
    }

    public function test_status_is_an_allow_list_on_save(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->tables->save(null, ['label' => 'X', 'status' => 'on-fire'], self::NOW);
    }

    public function test_set_status_moves_a_table_and_rejects_a_bad_status(): void
    {
        $id = $this->tables->save(null, ['label' => '3'], self::NOW);
        self::assertSame(1, $this->tables->setStatus($id, 'occupied', self::NOW));
        self::assertSame('occupied', $this->tables->get($id)['status']);

        $this->expectException(\InvalidArgumentException::class);
        $this->tables->setStatus($id, 'levitating', self::NOW);
    }

    public function test_over_posting_is_ignored(): void
    {
        $id = $this->tables->save(null, ['label' => 'G', 'id' => 4242, 'created_at' => '1900-01-01 00:00:00', 'evil' => 'x'], self::NOW);
        self::assertNotSame(4242, $id, 'the id is server-assigned');
        $t = $this->tables->get($id);
        self::assertSame(self::NOW, $t['created_at'], 'created_at is server-set, not over-posted');
        self::assertArrayNotHasKey('evil', $t);
    }

    public function test_all_filters_by_status(): void
    {
        $this->tables->save(null, ['label' => '1', 'status' => 'open'], self::NOW);
        $this->tables->save(null, ['label' => '2', 'status' => 'occupied'], self::NOW);
        $this->tables->save(null, ['label' => '3', 'status' => 'occupied'], self::NOW);

        self::assertCount(3, $this->tables->all());
        self::assertCount(2, $this->tables->all('occupied'));
        self::assertCount(1, $this->tables->all('open'));
        // An unknown status filter falls back to "all", never an injection.
        self::assertCount(3, $this->tables->all("' OR '1'='1"));
    }

    public function test_delete_is_total(): void
    {
        $id = $this->tables->save(null, ['label' => 'Gone'], self::NOW);
        self::assertSame(1, $this->tables->delete($id));
        self::assertNull($this->tables->get($id));
        self::assertSame(0, $this->tables->delete($id), 'a second delete is a no-op');
    }

    public function test_updating_a_missing_table_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->tables->save(424242, ['label' => 'Ghost'], self::NOW);
    }
}
