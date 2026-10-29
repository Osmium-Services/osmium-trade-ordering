<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/**
 * A trade customer's basket and the steps to an order: add a product, change a quantity, remove a line, submit.
 * Everything outside the order tables is passed in (the shop's product, the customer's terms, the excluded brands and the
 * tax rate), so this holds the rules and nothing else.
 */
final class TradeBasket
{
    public const MAX_QUANTITY = 9999;
    private const REFERENCE_TRIES = 3;

    /**
     * @param callable(int): ?array $product The shop's product (shop.product.get), or null
     * @param callable(int): array{discount_percent: float, payment_terms: ?string} $terms A customer's terms
     * @param callable(): int[] $excludedBrandIds Brands that get no trade discount
     * @param callable(): float $taxRatePercent The shop's tax rate
     * @param (callable(int): mixed)|null $afterSubmit Called with the order id once an order is submitted (the confirmation email)
     */
    public function __construct(
        private TradeOrder $orders,
        private TradeOrderItem $items,
        private $product,
        private $terms,
        private $excludedBrandIds,
        private $taxRatePercent,
        private $afterSubmit = null,
    ) {
    }

    /** @return array{success: bool, error?: string, item_id?: int, quantity?: int} */
    public function add(int $customerId, int $productId, int $quantity): array
    {
        $quantity = \min(self::MAX_QUANTITY, \max(1, $quantity));

        $product = ($this->product)($productId);
        $missing = $product === null || !$product['available'];
        if ($missing) return ['success' => false, 'error' => 'Product not found'];

        $terms = ($this->terms)($customerId);
        $draft = $this->orders->getDraft($customerId);
        $orderId = $draft === null ? $this->orders->createDraft(customerId: $customerId, discountPercent: $terms['discount_percent']) : (int) $draft['id'];

        $existing = $this->items->getByProduct(orderId: $orderId, productId: $productId);
        if ($existing !== null) return $this->changeQuantity(orderId: $orderId, itemId: (int) $existing['id'], quantity: (int) $existing['quantity'] + $quantity);

        $eligible = !\in_array((int) $product['brandId'], ($this->excludedBrandIds)(), true);
        $priced = TradePricing::line(
            basePriceExcTax: (float) $product['priceExcTax'],
            discountPercent: $terms['discount_percent'],
            brandEligible: $eligible,
            taxRatePercent: ($this->taxRatePercent)(),
            quantity: $quantity,
        );
        $itemId = $this->items->insert(orderId: $orderId, productId: $productId, line: $priced + [
            'product_ref' => (string) $product['reference'],
            'product_title' => \trim($product['brandName'] . ' ' . $product['name']),
            'quantity' => $quantity,
        ]);
        $this->orders->updateTotals($orderId);

        return ['success' => true, 'item_id' => $itemId, 'quantity' => $quantity];
    }

    /** A quantity under 1 removes the line. @return array{success: bool, error?: string, quantity?: int} */
    public function setQuantity(int $customerId, int $itemId, int $quantity): array
    {
        $draft = $this->orders->getDraft($customerId);
        $noDraft = $draft === null;
        if ($noDraft) return ['success' => false, 'error' => 'No draft order found'];

        $orderId = (int) $draft['id'];
        if ($quantity < 1) return $this->remove(customerId: $customerId, itemId: $itemId);

        return $this->changeQuantity(orderId: $orderId, itemId: $itemId, quantity: \min(self::MAX_QUANTITY, $quantity));
    }

    /** @return array{success: bool, error?: string} */
    public function remove(int $customerId, int $itemId): array
    {
        $draft = $this->orders->getDraft($customerId);
        $noDraft = $draft === null;
        if ($noDraft) return ['success' => false, 'error' => 'No draft order found'];

        $this->items->delete(orderId: (int) $draft['id'], itemId: $itemId);
        $this->orders->updateTotals((int) $draft['id']);

        return ['success' => true];
    }

    /** @return array{success: bool, error?: string, reference?: string} */
    public function submit(int $customerId, ?string $poReference, string $year): array
    {
        $draft = $this->orders->getDraft($customerId);
        $noDraft = $draft === null;
        if ($noDraft) return ['success' => false, 'error' => 'No draft order found'];

        $cannot = !TradeOrderStatus::canSubmit(status: (string) $draft['status'], itemCount: (int) $draft['item_count']);
        if ($cannot) return ['success' => false, 'error' => 'Cannot submit empty order'];

        $po = $poReference === null || \trim($poReference) === '' ? null : \trim($poReference);
        $terms = ($this->terms)($customerId);
        $taxRate = ($this->taxRatePercent)();

        for ($try = 0; $try < self::REFERENCE_TRIES; $try++) {
            $reference = TradePricing::reference(year: $year, sequence: TradePricing::nextSequence($this->orders->highestReference($year)));
            $submitted = $this->trySubmit(orderId: (int) $draft['id'], reference: $reference, po: $po, paymentTerms: $terms['payment_terms'], taxRate: $taxRate);
            if ($submitted) {
                $this->announce((int) $draft['id']);

                return ['success' => true, 'reference' => $reference];
            }
        }

        return ['success' => false, 'error' => 'Failed to submit order'];
    }

    /** The draft and its lines, for showing a basket. @return array{order: array<string, mixed>|null, items: array<int, array<string, mixed>>} */
    public function cart(int $customerId): array
    {
        $draft = $this->orders->getDraft($customerId);
        $noDraft = $draft === null;
        if ($noDraft) return ['order' => null, 'items' => []];

        return ['order' => $draft, 'items' => $this->items->listByOrder((int) $draft['id'])];
    }

    // Inside

    private function announce(int $orderId): void
    {
        $hasListener = $this->afterSubmit !== null;
        if ($hasListener) ($this->afterSubmit)($orderId);
    }

    /** @return array{success: bool, quantity: int} */
    private function changeQuantity(int $orderId, int $itemId, int $quantity): array
    {
        $quantity = \min(self::MAX_QUANTITY, $quantity);
        $this->items->setQuantity(orderId: $orderId, itemId: $itemId, quantity: $quantity);
        $this->orders->updateTotals($orderId);

        return ['success' => true, 'quantity' => $quantity];
    }

    /** False when the reference was taken at the same moment by another order (the unique key refused it) */
    private function trySubmit(int $orderId, string $reference, ?string $po, ?string $paymentTerms, float $taxRate): bool
    {
        try {
            return $this->orders->submit(orderId: $orderId, reference: $reference, poReference: $po, paymentTerms: $paymentTerms, taxRatePercent: $taxRate);
        } catch (\PDOException $e) {
            \error_log('Trade order reference clash: ' . $e->getMessage());

            return false;
        }
    }
}
