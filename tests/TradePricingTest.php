<?php

declare(strict_types=1);

namespace Tests\Services\TradeOrdering;

use Osmium\Services\TradeOrdering\Models\TradeOrderStatus;
use Osmium\Services\TradeOrdering\Models\TradePricing;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/TradePricing.php';
require_once __DIR__ . '/../models/TradeOrderStatus.php';

final class TradePricingTest extends TestCase
{
    public function test_an_eligible_brand_gets_the_discount_and_tax_comes_after(): void
    {
        $line = TradePricing::line(basePriceExcTax: 100.0, discountPercent: 12.5, brandEligible: true, taxRatePercent: 20.0, quantity: 3);

        $this->assertSame(['unit_exc_tax' => 87.5, 'unit_inc_tax' => 105.0, 'line_exc_tax' => 262.5, 'line_inc_tax' => 315.0], $line);
    }

    public function test_an_excluded_brand_or_no_discount_pays_the_shop_price(): void
    {
        $excluded = TradePricing::line(basePriceExcTax: 100.0, discountPercent: 12.5, brandEligible: false, taxRatePercent: 20.0, quantity: 1);
        $none = TradePricing::line(basePriceExcTax: 100.0, discountPercent: 0.0, brandEligible: true, taxRatePercent: 20.0, quantity: 1);

        $this->assertSame(100.0, $excluded['unit_exc_tax']);
        $this->assertSame(120.0, $excluded['unit_inc_tax']);
        $this->assertSame($excluded, $none);
    }

    public function test_each_figure_is_rounded_to_pence_from_the_unrounded_price_as_solargo2_does(): void
    {
        $line = TradePricing::line(basePriceExcTax: 19.99, discountPercent: 7.5, brandEligible: true, taxRatePercent: 20.0, quantity: 3);

        // 19.99 x 0.925 = 18.49075 and x 1.2 = 22.1889
        $this->assertSame(18.49, $line['unit_exc_tax']);
        $this->assertSame(22.19, $line['unit_inc_tax']);
        $this->assertSame(55.47, $line['line_exc_tax']);   // 18.49075 x 3 = 55.47225
        $this->assertSame(66.57, $line['line_inc_tax']);   // 22.1889 x 3 = 66.5667
    }

    public function test_totals_sum_the_lines_and_tax_is_the_difference(): void
    {
        $totals = TradePricing::totals([
            ['quantity' => 3, 'line_exc_tax' => '262.50', 'line_inc_tax' => '315.00'],
            ['quantity' => '2', 'line_exc_tax' => 10.0, 'line_inc_tax' => 12.0],
        ]);

        $this->assertSame(['item_count' => 5, 'subtotal_exc_tax' => 272.5, 'tax_amount' => 54.5, 'total_inc_tax' => 327.0], $totals);
        $this->assertSame(['item_count' => 0, 'subtotal_exc_tax' => 0.0, 'tax_amount' => 0.0, 'total_inc_tax' => 0.0], TradePricing::totals([]));
    }

    public function test_references_are_padded_and_the_next_number_follows_the_highest(): void
    {
        $this->assertSame('TO-2026-0007', TradePricing::reference('2026', 7));
        $this->assertSame('TO-2026-12345', TradePricing::reference('2026', 12345));
        $this->assertSame(1, TradePricing::nextSequence(null));
        $this->assertSame(8, TradePricing::nextSequence('TO-2026-0007'));
        $this->assertSame(10000, TradePricing::nextSequence('TO-2026-9999'));
    }

    public function test_only_a_draft_with_items_can_be_submitted(): void
    {
        $this->assertTrue(TradeOrderStatus::canSubmit('draft', 1));
        $this->assertFalse(TradeOrderStatus::canSubmit('draft', 0));
        $this->assertFalse(TradeOrderStatus::canSubmit('pending', 3));
        $this->assertTrue(TradeOrderStatus::isValid('shipped'));
        $this->assertFalse(TradeOrderStatus::isValid('lost'));
        $this->assertSame('Shipped', TradeOrderStatus::label('shipped'));
    }
}
