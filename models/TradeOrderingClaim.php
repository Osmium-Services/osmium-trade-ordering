<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/**
 * Answers core's `page.claim` hook for the trade ordering pages. The theme supplies the views (named relative to the
 * site's module path); the claim hands over the data. A claim cannot redirect, so a visitor who is not logged in is
 * shown the view with loggedIn false and the login address to link to. Every form posts to an api/trade-basket-* route,
 * which redirects back with a one-time message. A script that sends `Accept: application/json` to the same routes gets
 * {success, message or error, cart} instead (and `reference` after a submit), and api/trade-basket-cart returns the cart
 * alone: item_count, items (id, title, options_display, unit_price, quantity, line_total), subtotal, tax, total,
 * currency_sign. A theme can use these for a slide-in basket.
 *
 * Every view gets these in its array: loggedIn, loginUrl, customer (company_name, contact_name), currencySign, taxName,
 * productsUrl, basketUrl, ordersUrl, notice and error (one-time messages, or null), basketCount (items in the basket).
 *
 *   trade/views/products.phtml  $tradeProducts: discountPercent, sections (heading, path[], products[]), addAction.
 *                               A product has id, title, brandName, name, reference, image (or null), statusLabel,
 *                               priceExcTax (the shop price), tradePriceExcTax, tradePriceIncTax, discounted, canOrder,
 *                               hasOptions, options (the product's option types: id, label, defaultLabel, values[] with id,
 *                               label, premium), optionPremiumFactor (what an option's price is multiplied by: below 1
 *                               when the site discounts options), discountAllowed, available. Also taxRatePercent and
 *                               documentsUrl (put a product id after it to open that product's documents, or null when
 *                               Trade Downloads is not installed: show no Documents link then).
 *                               Add form: method post, fields product_id and quantity, and for a product with options
 *                               options[<type id>] = <value id>, where 0 is the type's default option
 *   trade/views/basket.phtml    $tradeBasket: order (the draft, or null), items, setAction (fields item_id and quantity;
 *                               a quantity of 0 removes the line), submitAction (field po_reference), paymentTerms
 *   trade/views/orders.phtml    $tradeOrders: orders (id, reference, status, statusLabel, submitted_at, created_at, item_count, subtotal_exc_tax,
 *                               tax_amount, total_inc_tax, discount_percent, po_reference, payment_terms, items[] with
 *                               product_title, product_ref, quantity, unit_price_exc_tax, line_total_exc_tax, and
 *                               hasDocument), pdfAction (form post, hidden field id: the order's PDF)
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
     * Each product with its option types; a product has options when it has any (the shop's own flag is not used)
     *
     * @param array<int, array<string, mixed>> $products
     * @param array<int, array<int, array<string, mixed>>> $options by product id
     * @return array<int, array<string, mixed>>
     */
    private static function withOptions(array $products, array $options): array
    {
        foreach ($products as &$product) {
            $types = $options[(int) $product['id']] ?? [];
            $product['options'] = $types;
            $product['hasOptions'] = $types !== [];
        }

        return $products;
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
                $modes = (new TradeCategoryRules($osmium->dataSource))->modes();
                $sections = TradeCatalogue::sections(self::withOptions(TradeOrderingSource::products($osmium), TradeOrderingSource::options($osmium)), $discount, $excluded, TradeOrderingSource::taxRatePercent($osmium), TradeOrderingSource::categories($osmium), $modes, TradeOrderingConfig::get()['discountOptions']);
            }

            return ['view' => 'trade/views/products.phtml', 'data' => ['tradeProducts' => $common + [
                'discountPercent' => $discount,
                'sections' => $sections,
                'addAction' => '/api/trade-basket-add' . $slash,
                'documentsUrl' => $loggedIn ? TradeOrderingSource::documentsUrl($context['osmium']) : null,
                'taxRatePercent' => $loggedIn ? TradeOrderingSource::taxRatePercent($context['osmium']) : 0.0,
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
            $db = $context['osmium']->dataSource;
            $itemModel = new TradeOrderItem($db);
            foreach ((new TradeOrder($db))->listForCustomer($context['customerId']) as $order) {
                $orders[] = $order + [
                    'statusLabel' => TradeOrderStatus::label((string) $order['status']),
                    'items' => $itemModel->listByOrder((int) $order['id']),
                    'hasDocument' => TradeOrderDocument::hasDocument($order),
                ];
            }
        }

        return ['view' => 'trade/views/orders.phtml', 'data' => ['tradeOrders' => $common + ['orders' => $orders, 'pdfAction' => '/api/trade-order-pdf' . $slash]]];
    }
}
