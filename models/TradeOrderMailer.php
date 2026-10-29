<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

use Osmium\Core\Library\BrandLogo;
use Osmium\Core\Library\MailerFactory;

/**
 * Sends the order emails to the customer through the site's chosen mail provider. A failure is logged and reported as
 * false; it never throws into the order flow.
 */
final class TradeOrderMailer
{
    public function __construct(private object $osmium)
    {
    }

    public function sendConfirmation(int $orderId): bool
    {
        return $this->sendFor(orderId: $orderId, build: static fn(array $order, array $items, string $sign, string $taxName, string $site, array $brand): array => TradeOrderMail::confirmation(order: $order, items: $items, sign: $sign, taxName: $taxName, siteName: $site, brand: $brand));
    }

    public function sendDispatched(int $orderId): bool
    {
        return $this->sendFor(orderId: $orderId, build: static fn(array $order, array $items, string $sign, string $taxName, string $site, array $brand): array => TradeOrderMail::dispatched(order: $order, items: $items, sign: $sign, taxName: $taxName, siteName: $site, brand: $brand));
    }

    private function sendFor(int $orderId, callable $build): bool
    {
        try {
            $db = $this->osmium->dataSource;
            $order = (new TradeOrder($db))->getForAdmin($orderId);
            $address = \trim((string) ($order['email'] ?? ''));
            if ($address === '') return false;

            $config = $this->osmium->config;
            $items = (new TradeOrderItem($db))->listByOrder($orderId);
            $brand = BrandLogo::emailVars(fqdn: (string) $config->site->FQDN, siteName: (string) $config->site->name);
            $mail = $build($order, $items, (string) ($config->shop->currencySign ?? '£'), (string) ($config->shop->taxName ?? 'VAT'), (string) $config->site->name, $brand);

            $result = MailerFactory::make($config)->send(
                recipients: [['email' => $address, 'name' => (string) ($order['contact_name'] ?? '')]],
                subject: $mail['subject'],
                htmlBody: $mail['html'],
            );
            $sent = !empty($result['success']);
            if (!$sent) \error_log('Trade Ordering: order email failed: ' . ($result['error'] ?? 'unknown error'));

            return $sent;
        } catch (\Throwable $e) {
            \error_log('Trade Ordering: order email failed: ' . $e->getMessage());

            return false;
        }
    }
}
