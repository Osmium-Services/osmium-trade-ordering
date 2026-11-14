<?php

declare(strict_types=1);

namespace Tests\Services\TradeOrdering;

use Osmium\Services\TradeOrdering\Models\TradeCatalogue;
use Osmium\Services\TradeOrdering\Models\TradeCategoryModes;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/TradePricing.php';
require_once __DIR__ . '/../models/TradeCategoryModes.php';
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

    /** A small tree: Panels (1) > Rigid (2), Accessories (3) > Controllers (4) > Victron (5), and a top-level Clearance (6) and Lighting (7) */
    private function tree(): array
    {
        $c = static fn(int $id, ?int $parent, string $name, array $path, array $order): array => ['id' => $id, 'parentId' => $parent, 'name' => $name, 'path' => $path, 'order' => $order];

        return [
            $c(1, null, 'Panels', ['Panels'], [0]),
            $c(2, 1, 'Rigid', ['Panels', 'Rigid'], [0, 0]),
            $c(3, 1, 'Accessories', ['Panels', 'Accessories'], [0, 1]),
            $c(4, 3, 'Controllers', ['Panels', 'Accessories', 'Controllers'], [0, 1, 0]),
            $c(5, 4, 'Victron', ['Panels', 'Accessories', 'Controllers', 'Victron'], [0, 1, 0, 0]),
            $c(6, null, 'Clearance', ['Clearance'], [2]),
            $c(7, null, 'Lighting', ['Lighting'], [1]),
        ];
    }

    private function inCategory(int $id, string $title): array
    {
        $category = null;
        foreach ($this->tree() as $candidate) {
            if ($candidate['id'] === $id) $category = $candidate;
        }

        return $this->product($id * 10, $title, category: $category);
    }

    public function test_sections_are_ordered_level_by_level_so_a_deep_category_can_come_before_a_shallow_one(): void
    {
        $products = [$this->inCategory(7, 'Lamp'), $this->inCategory(2, 'Panel'), $this->inCategory(6, 'Old stock')];

        $sections = TradeCatalogue::sections($products, 0.0, [], 20.0, $this->tree(), []);

        $this->assertSame(['Rigid', 'Lighting', 'Clearance'], \array_column($sections, 'heading'));
    }

    public function test_an_inside_category_is_shown_in_its_parents_section_all_the_way_up(): void
    {
        $modes = [5 => TradeCategoryModes::INSIDE];
        $sections = TradeCatalogue::sections([$this->inCategory(5, 'Victron charger'), $this->inCategory(4, 'Other charger')], 0.0, [], 20.0, $this->tree(), $modes);

        $this->assertSame(['Controllers'], \array_column($sections, 'heading'));
        $this->assertCount(2, $sections[0]['products']);

        $twoLevels = TradeCatalogue::sections([$this->inCategory(5, 'Victron charger')], 0.0, [], 20.0, $this->tree(), [5 => TradeCategoryModes::INSIDE, 4 => TradeCategoryModes::INSIDE]);
        $this->assertSame(['Accessories'], \array_column($twoLevels, 'heading'));
    }

    public function test_a_hidden_category_hides_its_products_and_those_of_categories_inside_it(): void
    {
        $products = [$this->inCategory(6, 'Old stock'), $this->inCategory(5, 'Victron charger'), $this->inCategory(2, 'Panel')];

        $sections = TradeCatalogue::sections($products, 0.0, [], 20.0, $this->tree(), [6 => TradeCategoryModes::HIDDEN, 3 => TradeCategoryModes::HIDDEN]);

        $this->assertSame(['Rigid'], \array_column($sections, 'heading'));
    }

    public function test_a_product_listed_under_two_categories_shows_in_each_but_not_twice_in_one(): void
    {
        $rigid = $this->category(1, 'Rigid', [1]);
        $bypass = $this->category(2, 'Bypass', [2]);
        $product = $this->product(1, 'Panel') + ['categories' => [$bypass, $rigid, $rigid]];
        $product['category'] = $bypass;

        $sections = TradeCatalogue::sections([$product], 0.0, [], 20.0, [$rigid + ['parentId' => null], $bypass + ['parentId' => null]]);

        $this->assertSame(['Rigid', 'Bypass'], \array_column($sections, 'heading'));
        $this->assertCount(1, $sections[0]['products']);
    }
}
