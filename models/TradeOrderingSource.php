<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

use Osmium\Core\Library\ServiceHooks;
use Osmium\Core\Library\ServiceLoader;
use Osmium\Core\Models\ServiceModel;

/**
 * Where the customer pages get their facts: who is logged in (Trade Accounts' trade.customer.current) and the shop's
 * products (shop.products.list, shop.product.get). Both come through hooks, so neither service's PHP is called.
 */
final class TradeOrderingSource
{
    /** @return array{id: int, company_name: string, contact_name: string, status: string}|null null when nobody is logged in */
    public static function customer(object $osmium): ?array
    {
        $customer = self::hooks($osmium)->handle(event: 'trade.customer.current', payload: ['osmium' => $osmium]);

        return \is_array($customer) ? $customer : null;
    }

    /** @return array<int, array<string, mixed>> every live product, as the shop describes it */
    public static function products(object $osmium): array
    {
        $products = self::hooks($osmium)->handle(event: 'shop.products.list', payload: ['dataSource' => $osmium->dataSource, 'osmium' => $osmium]);

        return \is_array($products) ? $products : [];
    }

    /** @return array<int, array{id: int, parentId: ?int, name: string, path: string[], order: int[]}> the shop's category tree, in order */
    public static function categories(object $osmium): array
    {
        $categories = self::hooks($osmium)->handle(event: 'shop.categories', payload: ['dataSource' => $osmium->dataSource]);

        return \is_array($categories) ? $categories : [];
    }

    /** @return array<string, mixed>|null one product, as shop.product.get gives it */
    public static function product(object $osmium, int $productId): ?array
    {
        $product = self::hooks($osmium)->handle(event: 'shop.product.get', payload: ['productId' => $productId, 'dataSource' => $osmium->dataSource]);

        return \is_array($product) ? $product : null;
    }

    /**
     * @param int $productId 0 for every product
     * @return array<int, array<int, array<string, mixed>>> option types by product id, as shop.product.options gives them
     */
    public static function options(object $osmium, int $productId = 0): array
    {
        $options = self::hooks($osmium)->handle(event: 'shop.product.options', payload: ['productId' => $productId, 'dataSource' => $osmium->dataSource]);

        return \is_array($options) ? $options : [];
    }

    /** @return string|null the address to put a product id after to open its documents (from Trade Downloads), or null when that service is not installed */
    public static function documentsUrl(object $osmium): ?string
    {
        $url = self::hooks($osmium)->handle(event: 'trade.documents.url', payload: ['osmium' => $osmium]);

        return \is_string($url) ? $url : null;
    }

    public static function taxRatePercent(object $osmium): float
    {
        return (float) ($osmium->config->shop->taxRatePercent ?? 0);
    }

    public static function currencySign(object $osmium): string
    {
        return (string) ($osmium->config->shop->currencySign ?? '£');
    }

    public static function taxName(object $osmium): string
    {
        return (string) ($osmium->config->shop->taxName ?? 'VAT');
    }

    private static function hooks(object $osmium): ServiceHooks
    {
        return new ServiceHooks(new ServiceLoader(new ServiceModel($osmium->dataSource)));
    }
}
