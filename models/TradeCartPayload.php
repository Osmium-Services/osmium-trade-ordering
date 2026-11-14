<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/** A trade customer's draft order as the slide-in basket shows it (the shape the live trade portal's basket uses). */
final class TradeCartPayload
{
    /**
     * @param array<string, mixed>|null $order the draft order, or null when there is none
     * @param array<int, array<string, mixed>> $items its lines
     * @return array{item_count: int, items: array<int, array<string, mixed>>, subtotal: string, tax: string, total: string, currency_sign: string}
     */
    public static function build(?array $order, array $items, string $currencySign): array
    {
        $lines = [];
        foreach ($items as $item) {
            $lines[] = [
                'id' => (int) $item['id'],
                'product_id' => (int) $item['product_id'],
                'title' => (string) $item['product_title'],
                'sku' => (string) $item['product_ref'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => $currencySign . \number_format((float) $item['unit_price_exc_tax'], 2),
                'line_total' => $currencySign . \number_format((float) $item['line_total_exc_tax'], 2),
                'options_display' => ($item['options_display'] ?? '') === '' ? null : $item['options_display'],
            ];
        }

        return [
            'item_count' => $order === null ? 0 : (int) $order['item_count'],
            'items' => $lines,
            'subtotal' => \number_format((float) ($order['subtotal_exc_tax'] ?? 0), 2),
            'tax' => \number_format((float) ($order['tax_amount'] ?? 0), 2),
            'total' => \number_format((float) ($order['total_inc_tax'] ?? 0), 2),
            'currency_sign' => $currencySign,
        ];
    }
}
