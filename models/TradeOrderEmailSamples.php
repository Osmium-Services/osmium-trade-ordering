<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

use Osmium\Core\Library\MailerFactory;

/**
 * Trade Ordering's emails for the admin Email settings test tool, offered through the email.samples hook. Each is the
 * real email built from made-up data (no order read or written) and sent to the address given and nobody else.
 */
final class TradeOrderEmailSamples
{
    /** @return array<int, array{id: string, label: string, group: string, handler: string}> */
    public static function samples(array $payload): array
    {
        return [
            ['id' => 'trade_ordering.confirmation', 'label' => 'Trade order confirmation (sample data)', 'group' => 'Trade Ordering', 'handler' => self::class . '::sendConfirmation'],
            ['id' => 'trade_ordering.dispatched', 'label' => 'Trade dispatch confirmation (sample data)', 'group' => 'Trade Ordering', 'handler' => self::class . '::sendDispatched'],
        ];
    }

    /** @return array{order: array<string, mixed>, items: array<int, array<string, mixed>>} */
    public static function data(): array
    {
        return [
            'order' => [
                'reference' => 'TO-SAMPLE-0001', 'po_reference' => 'PO-1234', 'payment_terms' => '30 Days', 'tax_rate_percent' => '20.00',
                'subtotal_exc_tax' => '262.50', 'tax_amount' => '52.50', 'total_inc_tax' => '315.00',
                'contact_name' => 'Jane Doe', 'company_name' => 'Sample Trade Ltd',
            ],
            'items' => [
                ['product_title' => 'Example Solar Panel 200W', 'product_ref' => 'EX-200', 'quantity' => 3, 'line_total_exc_tax' => '262.50'],
            ],
        ];
    }

    /** @param array{toEmail: string, osmium: object} $payload @return array{success: bool, error?: string} */
    public static function sendConfirmation(array $payload): array
    {
        $sample = self::data();
        $mail = TradeOrderMail::confirmation(order: $sample['order'], items: $sample['items'], sign: self::sign($payload), taxName: self::taxName($payload), siteName: (string) $payload['osmium']->config->site->name);

        return self::send(payload: $payload, mail: $mail);
    }

    /** @param array{toEmail: string, osmium: object} $payload @return array{success: bool, error?: string} */
    public static function sendDispatched(array $payload): array
    {
        $sample = self::data();
        $mail = TradeOrderMail::dispatched(order: $sample['order'], items: $sample['items'], sign: self::sign($payload), taxName: self::taxName($payload), siteName: (string) $payload['osmium']->config->site->name);

        return self::send(payload: $payload, mail: $mail);
    }

    private static function sign(array $payload): string
    {
        return (string) ($payload['osmium']->config->shop->currencySign ?? '£');
    }

    private static function taxName(array $payload): string
    {
        return (string) ($payload['osmium']->config->shop->taxName ?? 'VAT');
    }

    /** @param array{toEmail: string, osmium: object} $payload @param array{subject: string, html: string} $mail @return array{success: bool, error?: string} */
    private static function send(array $payload, array $mail): array
    {
        return MailerFactory::make($payload['osmium']->config)->send(
            recipients: [['email' => $payload['toEmail'], 'name' => '']],
            subject: $mail['subject'],
            htmlBody: $mail['html'],
        );
    }
}
