<?php

declare(strict_types=1);

namespace DanMat\Restaurant;

use Nimbus\Plugin\PluginStorage;

/**
 * Reports — read-only aggregation over orders, for the manager dashboard. Revenue
 * is summed from each order's recorded `amount_paid` (the authoritative settled
 * amount), never recomputed or client-supplied. Every method takes explicit date
 * bounds (a half-open `[from, to)` range) so the caller owns "today"/"this week" and
 * the queries stay deterministic and testable. Bound SQL throughout.
 */
final class Reports
{
    /** @param \Closure():PluginStorage $storage resolved lazily, so construction runs no query */
    public function __construct(private \Closure $storage)
    {
    }

    /**
     * Revenue and paid-order count for paid orders settled in `[from, to)`.
     *
     * @return array{revenue:string,orders:int}
     */
    public function revenueBetween(string $from, string $to): array
    {
        $row = $this->storage()->selectOne(
            'SELECT COALESCE(SUM(amount_paid), 0) AS revenue, COUNT(*) AS orders
             FROM ' . Schema::ORDER . ' WHERE paid = 1 AND paid_at >= :from AND paid_at < :to',
            ['from' => $from, 'to' => $to],
        );
        return [
            'revenue' => number_format((float) ($row['revenue'] ?? 0), 2, '.', ''),
            'orders'  => (int) ($row['orders'] ?? 0),
        ];
    }

    /**
     * Revenue per calendar day across `[from, to)`, oldest first.
     *
     * @return list<array{day:string,revenue:string,orders:int}>
     */
    public function revenueByDay(string $from, string $to): array
    {
        $rows = $this->storage()->select(
            'SELECT DATE(paid_at) AS day, COALESCE(SUM(amount_paid), 0) AS revenue, COUNT(*) AS orders
             FROM ' . Schema::ORDER . ' WHERE paid = 1 AND paid_at >= :from AND paid_at < :to
             GROUP BY DATE(paid_at) ORDER BY day',
            ['from' => $from, 'to' => $to],
        );
        return array_map(static fn (array $r): array => [
            'day'     => (string) $r['day'],
            'revenue' => number_format((float) $r['revenue'], 2, '.', ''),
            'orders'  => (int) $r['orders'],
        ], $rows);
    }

    /**
     * Best-selling items by quantity, over paid orders settled in `[from, to)`.
     *
     * @return list<array{name:string,qty:int,revenue:string}>
     */
    public function topItems(string $from, string $to, int $limit = 5): array
    {
        $limit = max(1, min($limit, 100));
        $rows  = $this->storage()->select(
            'SELECT i.name, SUM(i.qty) AS qty, SUM(i.unit_price * i.qty) AS revenue
             FROM ' . Schema::ORDER_ITEM . ' i JOIN ' . Schema::ORDER . ' o ON o.id = i.order_id
             WHERE o.paid = 1 AND o.paid_at >= :from AND o.paid_at < :to
             GROUP BY i.name ORDER BY qty DESC, revenue DESC LIMIT ' . $limit,
            ['from' => $from, 'to' => $to],
        );
        return array_map(static fn (array $r): array => [
            'name'    => (string) $r['name'],
            'qty'     => (int) $r['qty'],
            'revenue' => number_format((float) $r['revenue'], 2, '.', ''),
        ], $rows);
    }

    /** Orders not yet closed (still on the floor / in the kitchen / awaiting payment). */
    public function activeOrders(): int
    {
        $row = $this->storage()->selectOne(
            'SELECT COUNT(*) AS c FROM ' . Schema::ORDER . " WHERE status <> 'closed'",
        );
        return (int) ($row['c'] ?? 0);
    }

    private function storage(): PluginStorage
    {
        return ($this->storage)();
    }
}
