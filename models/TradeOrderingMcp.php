<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/**
 * Trade Ordering's tool for the /mcp endpoint, offered through core's `mcp.tools` hook: how trade orders are going
 * and whether any are waiting. Read only, and it never returns a customer, a PO reference or an address: counts,
 * values, references, statuses and dates only.
 */
class TradeOrderingMcp
{
    private const NEWEST = 10;

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>> tool descriptors
     */
    public static function tools(array $payload): array
    {
        return [
            [
                'name' => 'trade_orders_summary',
                'description' => 'How trade (business) orders are going: counts and values inc VAT per status for today, the last 7 days and the last 30 days by submitted date (drafts are baskets and are left out), how many orders are open, how many are pending and waiting to be confirmed with how many days the oldest has waited, this calendar month\'s total, and the newest 10 orders (reference, status, items, total, payment terms, submitted date). No customer names, PO references or addresses. Read only.',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()],
                'handler' => self::class . '::summary',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function summary(array $call): array
    {
        $orders = new TradeOrder($call['dataSource']);

        return self::shape($orders->periodTotals(), $orders->queue(), $orders->monthValue(), $orders->recentAnonymous(self::NEWEST));
    }

    /**
     * @param array<int, array<string, mixed>> $rows one row per status from order-period-totals.sql
     * @param array<string, mixed> $queue from order-queue.sql
     * @param array{total: float, orders: int} $month
     * @param array<int, array<string, mixed>> $newest from order-recent-anonymous.sql
     * @return array<string, mixed>
     */
    public static function shape(array $rows, array $queue, array $month, array $newest): array
    {
        return [
            'totals' => self::periods($rows),
            'queue' => [
                'open_orders' => (int) ($queue['open_orders'] ?? 0),
                'pending_orders' => (int) ($queue['pending_orders'] ?? 0),
                'oldest_pending_days' => isset($queue['oldest_pending_days']) ? (int) $queue['oldest_pending_days'] : null,
            ],
            'this_month' => ['orders' => (int) $month['orders'], 'value_inc_tax' => (float) $month['total']],
            'newest' => \array_map(static fn(array $o): array => [
                'reference' => (string) $o['reference'],
                'status' => (string) $o['status'],
                'items' => (int) $o['item_count'],
                'total_inc_tax' => (float) $o['total_inc_tax'],
                'payment_terms' => $o['payment_terms'],
                'submitted_at' => $o['submitted_at'],
            ], $newest),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<string, array{count: int, value: float}>>
     */
    public static function periods(array $rows): array
    {
        $periods = ['today' => [], 'last_7_days' => [], 'last_30_days' => []];
        $columns = ['today' => 'today', 'last_7_days' => 'week', 'last_30_days' => 'month'];

        foreach ($rows as $row) {
            foreach ($columns as $period => $prefix) {
                $count = (int) $row["{$prefix}_count"];
                if ($count === 0) continue;

                $periods[$period][(string) $row['status']] = ['count' => $count, 'value' => (float) $row["{$prefix}_value"]];
            }
        }

        return $periods;
    }
}
