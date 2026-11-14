<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/**
 * The trade price list: the shop's products with this customer's trade price on each, grouped into sections by the
 * shop's own category tree (sections by the category's sort numbers from the top, products with no category in "Other"
 * last, products by title within a section). Pure.
 *
 * A product can be put on an order when it is available (a product with options is configured first).
 */
final class TradeCatalogue
{
    public const OTHER = 'Other';

    /**
     * @param array<int, array<string, mixed>> $products as shop.products.list gives them
     * @param int[] $excludedBrandIds brands that get no trade discount
     * @param array<int, array{id: int, parentId: ?int, name: string, path: string[], order: int[]}> $categories as shop.categories gives them
     * @param array<int, string> $modes category id => TradeCategoryModes (a missing one is its own section)
     * @return array<int, array{heading: string, path: string[], products: array<int, array<string, mixed>>}>
     */
    public static function sections(array $products, float $discountPercent, array $excludedBrandIds, float $taxRatePercent, array $categories = [], array $modes = []): array
    {
        $map = [];
        foreach ($categories as $category) {
            $map[(int) $category['id']] = $category;
        }

        $byKey = [];
        foreach ($products as $product) {
            $priced = self::priced($product, $discountPercent, $excludedBrandIds, $taxRatePercent);

            // A product shows in every section it is listed under, once each
            $listedUnder = ($product['categories'] ?? []) !== [] ? $product['categories'] : [$product['category'] ?? null];
            foreach ($listedUnder as $category) {
                $section = self::sectionFor($category, $map, $modes);
                if ($section === false) continue; // Hidden

                $key = $section === null ? 'other' : 'c' . (int) $section['id'];

                $byKey[$key] ??= [
                    'heading' => $section === null ? self::OTHER : (string) $section['name'],
                    'path' => $section === null ? [] : \array_values($section['path']),
                    'order' => $section === null ? [] : \array_values($section['order']),
                    'products' => [],
                ];
                $listed = \in_array($priced['id'], \array_column($byKey[$key]['products'], 'id'), true);
                if (!$listed) $byKey[$key]['products'][] = $priced;
            }
        }

        $sections = \array_values($byKey);
        \usort($sections, self::compareSections(...));

        $result = [];
        foreach ($sections as $section) {
            \usort($section['products'], static fn(array $a, array $b): int => \strcasecmp((string) $a['title'], (string) $b['title']));
            $result[] = ['heading' => $section['heading'], 'path' => $section['path'], 'products' => $section['products']];
        }

        return $result;
    }

    /**
     * The section a product belongs in: its category, moved up past every "inside its parent" category, or false when it
     * or any category above it is hidden, or null when it has no category (the "Other" section).
     *
     * @param array{id: int}|null $category the product's category, as shop.products.list gives it
     * @param array<int, array{id: int, parentId: ?int, name: string, path: string[], order: int[]}> $map
     * @param array<int, string> $modes
     * @return array<string, mixed>|false|null
     */
    public static function sectionFor(?array $category, array $map, array $modes): array|false|null
    {
        if ($category === null) return null;

        $id = (int) $category['id'];
        $known = isset($map[$id]);
        if (!$known) return $category;

        for ($at = $id, $steps = 0; $at !== null && isset($map[$at]) && $steps < 50; $at = $map[$at]['parentId'], $steps++) {
            if (($modes[$at] ?? null) === TradeCategoryModes::HIDDEN) return false;
        }

        for ($at = $id, $steps = 0; ($modes[$at] ?? null) === TradeCategoryModes::INSIDE && $map[$at]['parentId'] !== null && isset($map[$map[$at]['parentId']]) && $steps < 50; $steps++) {
            $at = $map[$at]['parentId'];
        }

        return $map[$at];
    }

    /**
     * @param array<string, mixed> $product
     * @param int[] $excludedBrandIds
     * @return array<string, mixed> the product plus tradePriceExcTax, tradePriceIncTax, discounted and canOrder
     */
    public static function priced(array $product, float $discountPercent, array $excludedBrandIds, float $taxRatePercent): array
    {
        $eligible = !\in_array((int) $product['brandId'], $excludedBrandIds, true);
        $line = TradePricing::line(basePriceExcTax: (float) $product['priceExcTax'], discountPercent: $discountPercent, brandEligible: $eligible, taxRatePercent: $taxRatePercent, quantity: 1);

        return $product + [
            'tradePriceExcTax' => $line['unit_exc_tax'],
            'tradePriceIncTax' => $line['unit_inc_tax'],
            'discounted' => $eligible && $discountPercent > 0,
            'discountAllowed' => $eligible,
            'canOrder' => (bool) $product['available'],
        ];
    }

    /** @param array{heading: string, order: int[]} $a */
    private static function compareSections(array $a, array $b): int
    {
        $aOther = $a['order'] === [];
        $bOther = $b['order'] === [];
        if ($aOther !== $bOther) return $aOther ? 1 : -1;

        $byOrder = self::compareOrder($a['order'], $b['order']);
        if ($byOrder !== 0) return $byOrder;

        return \strcasecmp($a['heading'], $b['heading']);
    }

    /** Sort numbers from the top of the tree, one level at a time (a parent before its children). PHP's <=> on arrays would compare lengths first. */
    private static function compareOrder(array $a, array $b): int
    {
        foreach ($a as $level => $number) {
            if (!isset($b[$level])) return 1;
            if ($number !== $b[$level]) return $number <=> $b[$level];
        }

        return \count($a) <=> \count($b);
    }
}
