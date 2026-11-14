<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/**
 * The trade price list: the shop's products with this customer's trade price on each, grouped into sections by the
 * shop's own category tree (sections by the category's sort numbers from the top, products with no category in "Other"
 * last, products by title within a section). Pure.
 *
 * A product can be put on an order when it is available and has no options (configured products come later).
 */
final class TradeCatalogue
{
    public const OTHER = 'Other';

    /**
     * @param array<int, array<string, mixed>> $products as shop.products.list gives them
     * @param int[] $excludedBrandIds brands that get no trade discount
     * @return array<int, array{heading: string, path: string[], products: array<int, array<string, mixed>>}>
     */
    public static function sections(array $products, float $discountPercent, array $excludedBrandIds, float $taxRatePercent): array
    {
        $byKey = [];
        foreach ($products as $product) {
            $priced = self::priced($product, $discountPercent, $excludedBrandIds, $taxRatePercent);
            $category = $product['category'] ?? null;
            $key = $category === null ? 'other' : 'c' . (int) $category['id'];

            $byKey[$key] ??= [
                'heading' => $category === null ? self::OTHER : (string) $category['name'],
                'path' => $category === null ? [] : \array_values($category['path']),
                'order' => $category === null ? [] : \array_values($category['order']),
                'products' => [],
            ];
            $byKey[$key]['products'][] = $priced;
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
            'canOrder' => (bool) $product['available'] && !(bool) $product['hasOptions'],
        ];
    }

    /** @param array{heading: string, order: int[]} $a */
    private static function compareSections(array $a, array $b): int
    {
        $aOther = $a['order'] === [];
        $bOther = $b['order'] === [];
        if ($aOther !== $bOther) return $aOther ? 1 : -1;

        $byOrder = $a['order'] <=> $b['order'];
        if ($byOrder !== 0) return $byOrder;

        return \strcasecmp($a['heading'], $b['heading']);
    }
}
