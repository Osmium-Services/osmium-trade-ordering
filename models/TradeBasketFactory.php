<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/** Puts together a TradeBasket wired to the real shop, customer terms, excluded brands, tax rate and confirmation email. */
final class TradeBasketFactory
{
    public static function make(object $osmium): TradeBasket
    {
        $db = $osmium->dataSource;

        return new TradeBasket(
            orders: new TradeOrder($db),
            items: new TradeOrderItem($db),
            product: static fn(int $id): ?array => TradeOrderingSource::product($osmium, $id),
            options: static fn(int $id): array => TradeOrderingSource::options($osmium, $id)[$id] ?? [],
            discountOptions: static fn(): bool => TradeOrderingConfig::get()['discountOptions'],
            terms: static fn(int $customerId): array => self::terms($db, $customerId),
            excludedBrandIds: static fn(): array => (new TradeBrandExclusions($db))->ids(),
            taxRatePercent: static fn(): float => TradeOrderingSource::taxRatePercent($osmium),
            afterSubmit: static fn(int $orderId): bool => (new TradeOrderMailer($osmium))->sendConfirmation($orderId),
        );
    }

    /** @return array{discount_percent: float, payment_terms: ?string} a customer with no terms gets no discount and no stated terms */
    public static function terms(object $db, int $customerId): array
    {
        $row = null;
        foreach ((new TradeCustomerTerms($db))->getMany([$customerId]) as $candidate) {
            if ((int) $candidate['customer_id'] === $customerId) $row = $candidate;
        }

        return [
            'discount_percent' => $row === null ? 0.0 : (float) $row['discount_percent'],
            'payment_terms' => $row === null || ($row['payment_terms'] ?? '') === '' ? null : (string) $row['payment_terms'],
        ];
    }
}
