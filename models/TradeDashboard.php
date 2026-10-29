<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/** Hook handler for dashboard.widgets: reads the orders and hands them to the card. */
final class TradeDashboard
{
    /**
     * @param array{osmium: object, role?: string} $payload
     * @return array<string, mixed>
     */
    public static function widget(array $payload): array
    {
        $orders = new TradeOrder($payload['osmium']->dataSource);
        $pending = $orders->listForAdmin([TradeOrderStatus::PENDING]);
        $sign = (string) ($payload['osmium']->config->shop->currencySign ?? '£');

        return TradeDashboardCard::build(pending: $pending, month: $orders->monthValue(), sign: $sign);
    }
}
