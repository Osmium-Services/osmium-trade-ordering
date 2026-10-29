<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/** What goes on a trade order document, apart from the drawing itself: title, wording, and the lines of text. Pure. */
final class TradeInvoiceLayout
{
    public const DEFAULT_PAYMENT_TERMS = '30 Days';

    private const DOCUMENTS = [
        TradeOrderStatus::PENDING => ['title' => 'ORDER ACKNOWLEDGEMENT', 'prefix' => 'OrderAck', 'footer' => 'Thank you for your order. This document confirms receipt of your order.'],
        TradeOrderStatus::CONFIRMED => ['title' => 'ORDER CONFIRMATION', 'prefix' => 'OrderConf', 'footer' => 'This confirms your order. An invoice will be issued upon dispatch.'],
        TradeOrderStatus::PROCESSING => ['title' => 'ORDER CONFIRMATION', 'prefix' => 'OrderConf', 'footer' => 'This confirms your order. An invoice will be issued upon dispatch.'],
        TradeOrderStatus::SHIPPED => ['title' => 'INVOICE', 'prefix' => 'Invoice', 'footer' => 'Thank you for your business. Payment is due within the terms stated above.'],
        TradeOrderStatus::DELIVERED => ['title' => 'INVOICE', 'prefix' => 'Invoice', 'footer' => 'Thank you for your business. Payment is due within the terms stated above.'],
    ];

    /** Drafts and cancelled orders have no document */
    public static function hasDocument(string $status): bool
    {
        return isset(self::DOCUMENTS[$status]);
    }

    /** @return array{title: string, prefix: string, footer: string}|null */
    public static function document(string $status): ?array
    {
        return self::DOCUMENTS[$status] ?? null;
    }

    public static function filename(string $prefix, string $reference): string
    {
        return $prefix . '-' . \preg_replace('/[^A-Za-z0-9._-]/', '', $reference) . '.pdf';
    }

    /** An order that stored no terms prints the default ones */
    public static function paymentTerms(?string $stored): string
    {
        $blank = $stored === null || \trim($stored) === '';
        if ($blank) return self::DEFAULT_PAYMENT_TERMS;

        return \trim($stored);
    }

    public static function discountText(float $percent): string
    {
        if ($percent <= 0) return 'None';

        return self::number($percent) . '%';
    }

    /** "VAT (20%)", or just "VAT" for an order submitted before the rate was kept */
    public static function taxLabel(string $taxName, ?float $ratePercent): string
    {
        if ($ratePercent === null) return $taxName;

        return $taxName . ' (' . self::number($ratePercent) . '%)';
    }

    /**
     * @param array<string, mixed> $company the site's company settings (address, phone, email, companyNumber, vatNumber)
     * @return string[]
     */
    public static function companyLines(array $company, string $taxName): array
    {
        $lines = \array_map('trim', \explode(',', (string) ($company['address'] ?? '')));
        $lines[] = 'Tel: ' . ($company['phone'] ?? '');
        $lines[] = 'Email: ' . ($company['email'] ?? '');
        $number = \trim((string) ($company['companyNumber'] ?? ''));
        $vat = \trim((string) ($company['vatNumber'] ?? ''));
        if ($number !== '') $lines[] = 'Company No: ' . $number;
        if ($vat !== '') $lines[] = $taxName . ' Reg: ' . $vat;

        return \array_values(\array_filter($lines, static fn(string $line): bool => $line !== ''));
    }

    /**
     * @param array<string, mixed> $order with the customer's details joined on
     * @return string[] the bill-to block: company, contact, address (country only when not the UK), email
     */
    public static function billToLines(array $order): array
    {
        $country = (string) ($order['country'] ?? '');
        $abroad = $country !== '' && $country !== 'United Kingdom';
        $lines = [
            $order['company_name'] ?? '',
            $order['contact_name'] ?? '',
            $order['address_line_1'] ?? '',
            $order['address_line_2'] ?? '',
            $order['city'] ?? '',
            $order['county'] ?? '',
            $order['postcode'] ?? '',
            $abroad ? $country : '',
            $order['email'] ?? '',
        ];

        return \array_values(\array_filter(\array_map('strval', $lines), static fn(string $line): bool => \trim($line) !== ''));
    }

    /** 20.0 gives "20" and 5.5 gives "5.5" */
    private static function number(float $value): string
    {
        return \rtrim(\rtrim(\number_format($value, 2, '.', ''), '0'), '.');
    }
}
