<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/**
 * Answers `trade.category.layout`: for every shop category, the section the trade price list shows it in (its own, or the
 * one it is inside) or false when it is hidden, so another trade page (the documents list) groups products the same way.
 */
final class TradeCategoryLayout
{
    /**
     * @param array{osmium: object} $payload
     * @return array<int, array{id: int, name: string, path: string[], order: int[]}|false> category id => section
     */
    public static function answer(array $payload): array
    {
        $osmium = $payload['osmium'];
        $categories = TradeOrderingSource::categories($osmium);
        $modes = (new TradeCategoryRules($osmium->dataSource))->modes();

        $map = [];
        foreach ($categories as $category) {
            $map[(int) $category['id']] = $category;
        }

        $layout = [];
        foreach ($map as $id => $category) {
            $section = TradeCatalogue::sectionFor($category, $map, $modes);
            $layout[$id] = $section === false || $section === null ? false : [
                'id' => (int) $section['id'],
                'name' => (string) $section['name'],
                'path' => \array_values($section['path']),
                'order' => \array_values($section['order']),
            ];
        }

        return $layout;
    }
}
