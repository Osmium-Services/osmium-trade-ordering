<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/**
 * Checks the options a customer chose against the product's option types (shop.product.options) and works out what they
 * add to the price. An option's premium is added after the trade discount, so it is never discounted.
 */
final class TradeOptions
{
    /**
     * @param array<int, array{id: int, label: string, defaultLabel: ?string, values: array<int, array{id: int, label: string, premium: float, reference: string}>}> $types
     * @param array<int|string, int|string> $selected option type id => value id (0 is the type's default option)
     * @return array{data: array<int, array{type_id: int, type_label: string, value_id: int, value_label: string, premium: float}>, premium: float, json: string, display: string}|null null when a choice is not one the product offers
     */
    public static function choose(array $types, array $selected): ?array
    {
        $data = [];
        foreach ($types as $type) {
            $valueId = (int) ($selected[$type['id']] ?? ($type['defaultLabel'] !== null ? 0 : -1));

            if ($valueId === 0) {
                $noDefault = $type['defaultLabel'] === null;
                if ($noDefault) return null;

                $data[] = ['type_id' => $type['id'], 'type_label' => $type['label'], 'value_id' => 0, 'value_label' => $type['defaultLabel'], 'premium' => 0.0];
                continue;
            }

            $found = null;
            foreach ($type['values'] as $value) {
                if ($value['id'] === $valueId) $found = $value;
            }
            if ($found === null) return null;

            $data[] = ['type_id' => $type['id'], 'type_label' => $type['label'], 'value_id' => $found['id'], 'value_label' => $found['label'], 'premium' => $found['premium']];
        }

        \usort($data, static fn(array $a, array $b): int => $a['type_id'] <=> $b['type_id']);
        $parts = [];
        foreach ($data as $choice) {
            $parts[] = $choice['type_label'] . ': ' . $choice['value_label'];
        }

        return [
            'data' => $data,
            'premium' => \round(\array_sum(\array_column($data, 'premium')), 2),
            'json' => $data === [] ? '' : (string) \json_encode(['options' => $data], JSON_UNESCAPED_UNICODE),
            'display' => \implode(', ', $parts),
        ];
    }
}
