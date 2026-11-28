<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/** The dashboard row for trade orders: baskets and the last 24 hours, laid out like the shop's baskets row. Pure. */
final class TradeDashboardCard
{
    /**
     * @param array{active_count: int, active_value: float, converted_count: int, converted_value: float} $stats
     * @return array<string, mixed>
     */
    public static function build(array $stats, string $viewFile): array
    {
        return [
            'id' => 'osmium-trade-ordering',
            'viewFile' => $viewFile,
            'roles' => ['admin'],
            'order' => 21,
            'cols' => 'col-12',
            'data' => $stats,
        ];
    }
}
