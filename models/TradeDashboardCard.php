<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/** The dashboard card for trade orders: pending orders waiting, with this month's value. Pure. */
final class TradeDashboardCard
{
    private const MAX_LINES = 5;

    /**
     * @param array<int, array<string, mixed>> $pending pending orders (reference, company_name, total_inc_tax), newest first
     * @param array{total: float, orders: int} $month
     * @return array<string, mixed>
     */
    public static function build(array $pending, array $month, string $sign): array
    {
        $lines = [[
            'text' => 'This month',
            'sub' => $sign . \number_format($month['total'], 2) . ' from ' . $month['orders'] . ($month['orders'] === 1 ? ' order' : ' orders'),
            'route' => 'trade/orders',
        ]];

        foreach (\array_slice($pending, 0, self::MAX_LINES) as $row) {
            $lines[] = [
                'text' => (string) $row['reference'] . ' ' . (string) ($row['company_name'] ?? ''),
                'sub' => $sign . \number_format((float) $row['total_inc_tax'], 2),
                'route' => 'trade/orders',
            ];
        }

        return [
            'id' => 'osmium-trade-ordering',
            'title' => 'Trade orders',
            'icon' => 'bx-package',
            'roles' => ['admin'],
            'order' => 21,
            'count' => \count($pending),
            'link' => ['label' => 'Open trade orders', 'route' => 'trade/orders'],
            'lines' => $lines,
            'emptyText' => 'No trade orders waiting.',
        ];
    }
}
