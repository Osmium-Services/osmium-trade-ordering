<?php

declare(strict_types=1);

namespace Tests\Services\TradeOrdering;

use Osmium\Services\TradeOrdering\Models\TradeDashboardCard;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/TradeDashboardCard.php';

final class TradeDashboardCardTest extends TestCase
{
    public function test_the_card_counts_pending_orders_and_leads_with_the_months_value(): void
    {
        $pending = [
            ['reference' => 'TO-2026-0002', 'company_name' => 'Acme', 'total_inc_tax' => '324.00'],
            ['reference' => 'TO-2026-0001', 'company_name' => null, 'total_inc_tax' => '216.5'],
        ];

        $card = TradeDashboardCard::build(pending: $pending, month: ['total' => 1540.0, 'orders' => 3], sign: '£');

        $this->assertSame(2, $card['count']);
        $this->assertSame(['admin'], $card['roles']);
        $this->assertSame('This month', $card['lines'][0]['text']);
        $this->assertSame('£1,540.00 from 3 orders', $card['lines'][0]['sub']);
        $this->assertSame('TO-2026-0002 Acme', $card['lines'][1]['text']);
        $this->assertSame('£324.00', $card['lines'][1]['sub']);
        $this->assertSame('TO-2026-0001 ', $card['lines'][2]['text']);
        $this->assertSame('trade/orders', $card['link']['route']);
    }

    public function test_only_the_first_five_pending_orders_are_listed_but_all_are_counted(): void
    {
        $pending = [];
        for ($n = 1; $n <= 8; $n++) {
            $pending[] = ['reference' => 'TO-2026-000' . $n, 'company_name' => 'C', 'total_inc_tax' => 10];
        }

        $card = TradeDashboardCard::build(pending: $pending, month: ['total' => 0.0, 'orders' => 1], sign: '£');

        $this->assertSame(8, $card['count']);
        $this->assertCount(6, $card['lines']); // this month, then five orders
        $this->assertSame('£0.00 from 1 order', $card['lines'][0]['sub']);
    }
}
