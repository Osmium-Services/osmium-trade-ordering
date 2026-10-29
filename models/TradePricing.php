<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/**
 * Trade prices and order totals, worked out the way SolarGo2 does: the discount applies to the product's ex-tax price when
 * its brand is eligible, then each stored figure is rounded to pence on its own. Pure.
 */
final class TradePricing
{
    /**
     * @return array{unit_exc_tax: float, unit_inc_tax: float, line_exc_tax: float, line_inc_tax: float}
     */
    public static function line(float $basePriceExcTax, float $discountPercent, bool $brandEligible, float $taxRatePercent, int $quantity): array
    {
        $discounted = $brandEligible && $discountPercent > 0;
        $unitExc = $discounted ? $basePriceExcTax * (1 - $discountPercent / 100) : $basePriceExcTax;
        $unitInc = $unitExc * (1 + $taxRatePercent / 100);

        return [
            'unit_exc_tax' => \round($unitExc, 2),
            'unit_inc_tax' => \round($unitInc, 2),
            'line_exc_tax' => \round($unitExc * $quantity, 2),
            'line_inc_tax' => \round($unitInc * $quantity, 2),
        ];
    }

    /**
     * @param array<int, array{quantity: int|string, line_exc_tax: float|string, line_inc_tax: float|string}> $lines
     * @return array{item_count: int, subtotal_exc_tax: float, tax_amount: float, total_inc_tax: float}
     */
    public static function totals(array $lines): array
    {
        $count = 0;
        $exc = 0.0;
        $inc = 0.0;
        foreach ($lines as $line) {
            $count += (int) $line['quantity'];
            $exc += (float) $line['line_exc_tax'];
            $inc += (float) $line['line_inc_tax'];
        }

        return [
            'item_count' => $count,
            'subtotal_exc_tax' => \round($exc, 2),
            'tax_amount' => \round($inc - $exc, 2),
            'total_inc_tax' => \round($inc, 2),
        ];
    }

    /** TO-2026-0007: the order reference for a year and the next number in it. */
    public static function reference(string $year, int $sequence): string
    {
        return 'TO-' . $year . '-' . \str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /** The next number in a year's references, from the highest existing reference (or none). */
    public static function nextSequence(?string $highestReference): int
    {
        $none = $highestReference === null || \strlen($highestReference) < 9;
        if ($none) return 1;

        return ((int) \substr($highestReference, 8)) + 1;
    }
}
