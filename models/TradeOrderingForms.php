<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/**
 * The handlers behind the customer pages' forms (api/trade-basket-add, -set and -submit). Each one needs a POST from a
 * logged-in trade customer, does one thing through TradeBasket, leaves a one-time message and sends the customer back to
 * the page they came from. Core accepts only same-origin POSTs on api routes.
 */
final class TradeOrderingForms
{
    /** POST: product_id, quantity */
    public static function add(object $osmium): never
    {
        $customer = self::customer($osmium);
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = \max(1, (int) ($_POST['quantity'] ?? 1));

        $result = TradeBasketFactory::make($osmium)->add(customerId: (int) $customer['id'], productId: $productId, quantity: $quantity);
        $ok = $result['success'];

        self::done($osmium, self::path($osmium, 'products'), $ok ? ['notice' => 'Added to your order.'] : ['error' => $result['error'] ?? 'Could not add that product.']);
    }

    /** POST: item_id, quantity (0 removes the line) */
    public static function set(object $osmium): never
    {
        $customer = self::customer($osmium);
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 0);

        $result = TradeBasketFactory::make($osmium)->setQuantity(customerId: (int) $customer['id'], itemId: $itemId, quantity: $quantity);
        $ok = $result['success'];

        self::done($osmium, self::path($osmium, 'basket'), $ok ? ['notice' => 'Your order has been updated.'] : ['error' => $result['error'] ?? 'Could not update your order.']);
    }

    /** POST: po_reference (optional) */
    public static function submit(object $osmium): never
    {
        $customer = self::customer($osmium);
        $po = \substr(\trim((string) ($_POST['po_reference'] ?? '')), 0, 100);

        $result = TradeBasketFactory::make($osmium)->submit(customerId: (int) $customer['id'], poReference: $po === '' ? null : $po, year: \date('Y'));
        $ok = $result['success'];

        $ok
            ? self::done($osmium, self::path($osmium, 'orders'), ['notice' => 'Thank you. Your order ' . $result['reference'] . ' has been submitted.'])
            : self::done($osmium, self::path($osmium, 'basket'), ['error' => $result['error'] ?? 'Could not submit your order.']);
    }

    /** The logged-in customer, or the visitor goes to the login page */
    private static function customer(object $osmium): array
    {
        $notPost = ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST';
        $customer = TradeOrderingSource::customer($osmium);
        $refused = $notPost || $customer === null;
        if ($refused) self::go($osmium, self::path($osmium, 'login'));

        return $customer;
    }

    private static function path(object $osmium, string $page): string
    {
        $slash = ($osmium->config->site->urlTrailingSlash ?? false) ? '/' : '';

        return '/trade/' . $page . $slash;
    }

    /** @param array<string, mixed> $flash */
    private static function done(object $osmium, string $to, array $flash): never
    {
        TradeOrderingFlash::set($_SESSION, $flash);
        self::go($osmium, $to);
    }

    private static function go(object $osmium, string $to): never
    {
        $osmium->header->redirect(targetURL: $to, statusCode: 302);
        exit; // redirect() ends the request; this only satisfies "never"
    }
}
