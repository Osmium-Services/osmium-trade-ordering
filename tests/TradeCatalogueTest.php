<?php

declare(strict_types=1);

namespace Tests\Services\TradeOrdering;

use Osmium\Services\TradeOrdering\Models\TradeCatalogue;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/TradePricing.php';
require_once __DIR__ . '/../models/TradeCatalogue.php';

final class TradeCatalogueTest extends TestCase
{
    private function product(int $id, string $title, int $brandId = 1, ?array $category = null, bool $available = true, bool $hasOptions = false, float $price = 100.0): array
    {
        return ['id' => $id, 'title' => $title, 'brandId' => $brandId, 'priceExcTax' => $price, 'available' => $available, 'hasOptions' => $hasOptions, 'category' => $category];
    }

    private function category(int $id, string $name, array $order): array
    {
        return ['id' => $id, 'name' => $name, 'path' => [$name], 'order' => $order];
    }

    public function test_a_product_gets_its_trade_price_and_an_excluded_brand_does_not(): void
    {
        $eligible = TradeCatalogue::priced($this->product(1, 'A'), 10.0, [], 20.0);
        $excluded = TradeCatalogue::priced($this->product(2, 'B', brandId: 5), 10.0, [5], 20.0);

        $this->assertSame(90.0, $eligible['tradePriceExcTax']);
        $this->assertSame(108.0, $eligible['tradePriceIncTax']);
        $this->assertTrue($eligible['discounted']);
        $this->assertSame(100.0, $excluded['tradePriceExcTax']);
        $this->assertFalse($excluded['discounted']);
    }

    public function test_only_available_products_without_options_can_be_ordered(): void
    {
        $this->assertTrue(TradeCatalogue::priced($this->product(1, 'A'), 0.0, [], 20.0)['canOrder']);
        $this->assertFalse(TradeCatalogue::priced($this->product(2, 'B', available: false), 0.0, [], 20.0)['canOrder']);
        $this->assertFalse(TradeCatalogue::priced($this->product(3, 'C', hasOptions: true), 0.0, [], 20.0)['canOrder']);
    }

    public function test_sections_follow_the_category_order_with_no_category_last_and_products_by_title(): void
    {
        $lighting = $this->category(9, 'Lighting', [3]);
        $panels = $this->category(2, 'Panels', [1]);
        $products = [
            $this->product(1, 'Zed light', category: $lighting),
            $this->product(2, 'Loose item'),
            $this->product(3, 'Beta panel', category: $panels),
            $this->product(4, 'alpha panel', category: $panels),
        ];

        $sections = TradeCatalogue::sections($products, 0.0, [], 20.0);

        $this->assertSame(['Panels', 'Lighting', 'Other'], \array_column($sections, 'heading'));
        $this->assertSame(['alpha panel', 'Beta panel'], \array_column($sections[0]['products'], 'title'));
    }
}
