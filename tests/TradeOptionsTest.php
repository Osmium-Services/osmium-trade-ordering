<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TradeOrdering;

use Osmium\Services\TradeOrdering\Models\TradeOptions;
use Osmium\Services\TradeOrdering\Models\TradePricing;
use PHPUnit\Framework\TestCase;

require_once 'app/services/osmium-trade-ordering/models/TradeOptions.php';
require_once 'app/services/osmium-trade-ordering/models/TradePricing.php';

final class TradeOptionsTest extends TestCase
{
    private function types(): array
    {
        return [['id' => 1, 'label' => 'Units', 'defaultLabel' => 'Single Panel', 'values' => [['id' => 5, 'label' => '5 Panel Pack', 'premium' => 235.83, 'reference' => 'X']]]];
    }

    public function test_the_default_option_costs_nothing_and_a_value_adds_its_premium(): void
    {
        $default = TradeOptions::choose($this->types(), [1 => '0']);
        $pack = TradeOptions::choose($this->types(), [1 => '5']);

        $this->assertSame(0.0, $default['premium']);
        $this->assertSame('Units: Single Panel', $default['display']);
        $this->assertSame(235.83, $pack['premium']);
        $this->assertSame('Units: 5 Panel Pack', $pack['display']);
    }

    public function test_a_choice_the_product_does_not_offer_is_refused_and_a_missing_one_takes_the_default(): void
    {
        $this->assertNull(TradeOptions::choose($this->types(), [1 => '99']));
        $this->assertSame('Units: Single Panel', TradeOptions::choose($this->types(), [])['display']);
    }

    public function test_a_product_with_no_options_has_an_empty_choice(): void
    {
        $this->assertSame(['data' => [], 'premium' => 0.0, 'json' => '', 'display' => ''], TradeOptions::choose([], []));
    }

    public function test_the_premium_is_added_after_the_discount(): void
    {
        $line = TradePricing::line(basePriceExcTax: 100.0, discountPercent: 25.0, brandEligible: true, taxRatePercent: 20.0, quantity: 2, premiumExcTax: 10.0);

        $this->assertSame(85.0, $line['unit_exc_tax']);
        $this->assertSame(170.0, $line['line_exc_tax']);
    }

    public function test_the_premium_is_discounted_too_when_the_site_says_so(): void
    {
        $line = TradePricing::line(basePriceExcTax: 100.0, discountPercent: 25.0, brandEligible: true, taxRatePercent: 20.0, quantity: 1, premiumExcTax: 10.0, discountPremium: true);

        $this->assertSame(82.5, $line['unit_exc_tax']);
    }
}
