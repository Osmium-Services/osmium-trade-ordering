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
    /** POST: product_id, quantity, options[option type id] = option value id (for a product with options) */
    public static function add(object $osmium): never
    {
        $customer = self::customer($osmium);
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = \max(1, (int) ($_POST['quantity'] ?? 1));

        $selected = \is_array($_POST['options'] ?? null) ? $_POST['options'] : [];

        $result = TradeBasketFactory::make($osmium)->add(customerId: (int) $customer['id'], productId: $productId, quantity: $quantity, selected: $selected);
        $ok = $result['success'];

        self::done($osmium, $customer, self::path($osmium, 'products'), $ok ? ['notice' => 'Added to your order.'] : ['error' => $result['error'] ?? 'Could not add that product.']);
    }

    /** POST: item_id, quantity (0 removes the line) */
    public static function set(object $osmium): never
    {
        $customer = self::customer($osmium);
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 0);

        $result = TradeBasketFactory::make($osmium)->setQuantity(customerId: (int) $customer['id'], itemId: $itemId, quantity: $quantity);
        $ok = $result['success'];

        self::done($osmium, $customer, self::path($osmium, 'basket'), $ok ? ['notice' => 'Your order has been updated.'] : ['error' => $result['error'] ?? 'Could not update your order.']);
    }

    /** POST: po_reference (optional) */
    public static function submit(object $osmium): never
    {
        $customer = self::customer($osmium);
        $po = \substr(\trim((string) ($_POST['po_reference'] ?? '')), 0, 100);

        $result = TradeBasketFactory::make($osmium)->submit(customerId: (int) $customer['id'], poReference: $po === '' ? null : $po, year: \date('Y'));
        $ok = $result['success'];

        $ok
            ? self::done($osmium, $customer, self::path($osmium, 'orders'), ['notice' => 'Thank you. Your order ' . $result['reference'] . ' has been submitted.'], ['reference' => $result['reference']])
            : self::done($osmium, $customer, self::path($osmium, 'basket'), ['error' => $result['error'] ?? 'Could not submit your order.']);
    }

    /** POST (JSON): the customer's draft order, for the slide-in basket */
    public static function cart(object $osmium): never
    {
        $customer = self::customer($osmium);

        self::json(['success' => true, 'cart' => self::cartPayload($osmium, (int) $customer['id'])]);
    }

    /** POST: id. The customer's own order as a PDF; any other order (or one with no document) is a plain-text refusal */
    public static function pdf(object $osmium): never
    {
        $customer = self::customer($osmium);
        $order = (new TradeOrder($osmium->dataSource))->getForAdmin((int) ($_POST['id'] ?? 0));
        $theirs = $order !== null && (int) $order['customer_id'] === (int) $customer['id'] && $order['status'] !== TradeOrderStatus::DRAFT;
        $found = $theirs && TradeOrderDocument::hasDocument($order);

        if (!$found) {
            \http_response_code(404);
            \header('Content-Type: text/plain; charset=utf-8');
            echo 'No document found for that order.';
            exit;
        }

        TradeOrderDocument::send($osmium, $order);
    }

    /** The logged-in customer, or the visitor goes to the login page (a script asking for JSON is told so instead) */
    private static function customer(object $osmium): array
    {
        $notPost = ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST';
        $customer = TradeOrderingSource::customer($osmium);
        $refused = $notPost || $customer === null;
        if ($refused && self::wantsJson()) self::json(['success' => false, 'error' => 'Not authenticated']);
        if ($refused) self::go($osmium, self::path($osmium, 'login'));

        return $customer;
    }

    private static function path(object $osmium, string $page): string
    {
        $slash = ($osmium->config->site->urlTrailingSlash ?? false) ? '/' : '';

        return '/trade/' . $page . $slash;
    }

    /**
     * A page asked for with a form is left with a one-time message and sent on; the slide-in basket asks for JSON and gets the
     * result and the new basket back.
     *
     * @param array<string, mixed> $flash notice or error
     * @param array<string, mixed> $extra more for the JSON answer
     */
    private static function done(object $osmium, array $customer, string $to, array $flash, array $extra = []): never
    {
        if (self::wantsJson()) {
            $failed = isset($flash['error']);
            self::json(['success' => !$failed, ($failed ? 'error' : 'message') => $flash['error'] ?? $flash['notice'], 'cart' => self::cartPayload($osmium, (int) $customer['id'])] + $extra);
        }

        TradeOrderingFlash::set($_SESSION, $flash);
        self::go($osmium, $to);
    }

    private static function wantsJson(): bool
    {
        return \str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    /** @return array<string, mixed> */
    private static function cartPayload(object $osmium, int $customerId): array
    {
        $cart = TradeBasketFactory::make($osmium)->cart($customerId);

        return TradeCartPayload::build($cart['order'], $cart['items'], TradeOrderingSource::currencySign($osmium));
    }

    /** @param array<string, mixed> $data */
    private static function json(array $data): never
    {
        \header('Content-Type: application/json; charset=utf-8');
        echo \json_encode($data);
        exit;
    }

    private static function go(object $osmium, string $to): never
    {
        $osmium->header->redirect(targetURL: $to, statusCode: 302);
        exit; // redirect() ends the request; this only satisfies "never"
    }
}
