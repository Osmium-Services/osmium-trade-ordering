<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/** Hook handler for dashboard.widgets: reads the basket and order numbers and hands them to the row. */
final class TradeDashboard
{
    /**
     * @param array{osmium: object, role?: string} $payload
     * @return array<string, mixed>
     */
    public static function widget(array $payload): array
    {
        $orders = new TradeOrder($payload['osmium']->dataSource);

        return TradeDashboardCard::build(stats: $orders->dashboardStats(), viewFile: __DIR__ . '/../views/widgets/trade-stats.phtml');
    }
}
