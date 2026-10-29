<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/**
 * What a trade customer's terms can be, and how they are described to Trade Accounts' customer form. Pure.
 */
final class TradeTermsRules
{
    public const DISCOUNT = 'ordering_discount_percent';
    public const PAYMENT = 'ordering_payment_terms';

    /** Value stored => label shown. An empty value means none chosen. */
    public const PAYMENT_TERMS = [
        '' => '-- Select --',
        'COD' => 'Cash on Delivery',
        '7 Days' => '7 Days',
        '14 Days' => '14 Days',
        '30 Days' => '30 Days',
        '60 Days' => '60 Days',
    ];

    /** @return array<int, array<string, mixed>> The two fields for trade.customer.fields */
    public static function fields(): array
    {
        return [
            [
                'name' => self::DISCOUNT,
                'label' => 'Trade Discount %',
                'type' => 'number',
                'min' => 0,
                'max' => 100,
                'step' => '0.5',
                'default' => '0',
                'help' => 'Percentage off eligible products',
            ],
            [
                'name' => self::PAYMENT,
                'label' => 'Payment Terms',
                'type' => 'select',
                'options' => self::PAYMENT_TERMS,
            ],
        ];
    }

    /**
     * The terms rows as the form shows them.
     *
     * @param array<int, array<string, mixed>> $rows customer_id, discount_percent, payment_terms
     * @return array<int, array<string, string>> customerId => [field name => value]
     */
    public static function values(array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[(int) $row['customer_id']] = [
                self::DISCOUNT => \rtrim(\rtrim(\number_format((float) $row['discount_percent'], 2, '.', ''), '0'), '.') ?: '0',
                self::PAYMENT => (string) ($row['payment_terms'] ?? ''),
            ];
        }

        return $values;
    }

    /** @param array<string, string> $values What the form posted @return string|null The first problem, or null when usable */
    public static function problem(array $values): ?string
    {
        $discount = \trim((string) ($values[self::DISCOUNT] ?? ''));
        $badNumber = $discount !== '' && !\is_numeric($discount);
        if ($badNumber) return 'The trade discount must be a number';

        $outOfRange = $discount !== '' && ((float) $discount < 0 || (float) $discount > 100);
        if ($outOfRange) return 'The trade discount must be between 0 and 100';

        $terms = (string) ($values[self::PAYMENT] ?? '');
        $unknownTerms = !\array_key_exists($terms, self::PAYMENT_TERMS);
        if ($unknownTerms) return 'Choose one of the listed payment terms';

        return null;
    }

    /**
     * @param array<string, string> $values Already checked with problem()
     * @return array{discount_percent: float, payment_terms: string|null}
     */
    public static function clean(array $values): array
    {
        $discount = \trim((string) ($values[self::DISCOUNT] ?? ''));
        $terms = (string) ($values[self::PAYMENT] ?? '');

        return [
            'discount_percent' => $discount === '' ? 0.0 : \round((float) $discount, 2),
            'payment_terms' => $terms === '' ? null : $terms,
        ];
    }
}
