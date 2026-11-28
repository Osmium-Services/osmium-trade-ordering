<?php

declare(strict_types=1);

namespace Tests\Services\TradeOrdering;

use Osmium\Services\TradeOrdering\Models\TradeDashboardCard;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/TradeDashboardCard.php';

final class TradeDashboardCardTest extends TestCase
{
    public function test_the_row_carries_the_four_numbers_and_its_own_view_for_admins(): void
    {
        $stats = ['active_count' => 2, 'active_value' => 540.5, 'converted_count' => 3, 'converted_value' => 1540.0];

        $card = TradeDashboardCard::build(stats: $stats, viewFile: '/x/trade-stats.phtml');

        $this->assertSame($stats, $card['data']);
        $this->assertSame('/x/trade-stats.phtml', $card['viewFile']);
        $this->assertSame(['admin'], $card['roles']);
        $this->assertSame('col-12', $card['cols']);
    }
}
