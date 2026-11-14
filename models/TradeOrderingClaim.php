<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/**
 * Answers core's `page.claim` hook for the trade ordering pages. The theme supplies the views (named relative to the
 * site's module path); the claim hands over the data. A claim cannot redirect, so a visitor who is not logged in is
 * shown the view with loggedIn false and the login address to link to. Every form posts to an api/trade-basket-* route,
 * which redirects back with a one-time message.
 *
 * Every view gets these in its array: loggedIn, loginUrl, customer (company_name, contact_name), currencySign, taxName,
 * productsUrl, basketUrl, ordersUrl, notice and error (one-time messages, or null), basketCount (items in the basket).
 *
 *   trade/views/products.phtml  $tradeProducts: discountPercent, sections (heading, path[], products[]), addAction.
 *                               A product has id, title, brandName, name, reference, image (or null), statusLabel,
 *                               priceExcTax (the shop price), tradePriceExcTax, tradePriceIncTax, discounted, canOrder,
 *                               hasOptions, available. Add form: method post, fields product_id and quantity
 *   trade/views/basket.phtml    $tradeBasket: order (the draft, or null), items, setAction (fields item_id and quantity;
 *                               a quantity of 0 removes the line), submitAction (field po_reference), paymentTerms
 *   trade/views/orders.phtml    $tradeOrders: orders (reference, status, statusLabel, submitted_at, item_count,
 *                               total_inc_tax, subtotal_exc_tax, po_reference, payment_terms)
 */
final class TradeOrderingClaim
{
    /** @param array{pageId: int, slug: string, osmium: object, data?: array} $payload */
    public static function page(array $payload): ?array
    {
        $slug = $payload['slug'];
        $isOurs = \in_array($slug, TradeOrderingPages::urls(), true);
        if (!$isOurs) return null;

        $osmium = $payload['osmium'];
        $slash = ($osmium->config->site->urlTrailingSlash ?? false) ? '/' : '';
        $customer = TradeOrderingSource::customer($osmium);
        $flash = TradeOrderingFlash::take($_SESSION);

        $common = [
            'loggedIn' => $customer !== null,
            'loginUrl' => '/trade/login' . $slash,
            'customer' => ['company_name' => (string) ($customer['company_name'] ?? ''), 'contact_name' => (string) ($customer['contact_name'] ?? '')],
            'currencySign' => TradeOrderingSource::currencySign($osmium),
            'taxName' => TradeOrderingSource::taxName($osmium),
            'productsUrl' => '/trade/products' . $slash,
            'basketUrl' => '/trade/basket' . $slash,
            'ordersUrl' => '/trade/orders' . $slash,
            'notice' => $flash['notice'] ?? null,
            'error' => $flash['error'] ?? null,
            'basketCount' => 0,
        ];

        $loggedOut = $customer === null;
        if ($loggedOut) return self::view($slug, $common, [], $slash);

        $customerId = (int) $customer['id'];
        $draft = (new TradeOrder($osmium->dataSource))->getDraft($customerId);
        $common['basketCount'] = $draft === null ? 0 : (int) $draft['item_count'];

        return self::view($slug, $common, ['osmium' => $osmium, 'customerId' => $customerId, 'draft' => $draft], $slash);
    }

    /**
     * @param array<string, mixed> $common
     * @param array<string, mixed> $context
     * @return array{view: string, data: array<string, mixed>}
     */
    private static function view(string $slug, array $common, array $context, string $slash): array
    {
        $loggedIn = $common['loggedIn'];

        if ($slug === TradeOrderingPages::PRODUCTS) {
            $sections = [];
            $discount = 0.0;
            if ($loggedIn) {
                $osmium = $context['osmium'];
                $discount = TradeBasketFactory::terms($osmium->dataSource, $context['customerId'])['discount_percent'];
                $excluded = (new TradeBrandExclusions($osmium->dataSource))->ids();
                $sections = TradeCatalogue::sections(TradeOrderingSource::products($osmium), $discount, $excluded, TradeOrderingSource::taxRatePercent($osmium));
            }

            return ['view' => 'trade/views/products.phtml', 'data' => ['tradeProducts' => $common + [
                'discountPercent' => $discount,
                'sections' => $sections,
                'addAction' => '/api/trade-basket-add' . $slash,
            ]]];
        }

        if ($slug === TradeOrderingPages::BASKET) {
            $items = [];
            $terms = ['discount_percent' => 0.0, 'payment_terms' => null];
            if ($loggedIn) {
                $osmium = $context['osmium'];
                $items = $context['draft'] === null ? [] : (new TradeOrderItem($osmium->dataSource))->listByOrder((int) $context['draft']['id']);
                $terms = TradeBasketFactory::terms($osmium->dataSource, $context['customerId']);
            }

            return ['view' => 'trade/views/basket.phtml', 'data' => ['tradeBasket' => $common + [
                'order' => $context['draft'] ?? null,
                'items' => $items,
                'paymentTerms' => $terms['payment_terms'],
                'setAction' => '/api/trade-basket-set' . $slash,
                'submitAction' => '/api/trade-basket-submit' . $slash,
            ]]];
        }

        $orders = [];
        if ($loggedIn) {
            foreach ((new TradeOrder($context['osmium']->dataSource))->listForCustomer($context['customerId']) as $order) {
                $orders[] = $order + ['statusLabel' => TradeOrderStatus::label((string) $order['status'])];
            }
        }

        return ['view' => 'trade/views/orders.phtml', 'data' => ['tradeOrders' => $common + ['orders' => $orders]]];
    }
}
