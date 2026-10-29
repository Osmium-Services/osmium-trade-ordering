<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/** The two emails a trade customer gets about an order: it was received, and it was dispatched. Pure. */
final class TradeOrderMail
{
    /**
     * @param array<string, mixed> $order with the customer's name and email joined on
     * @param array<int, array<string, mixed>> $items
     * @return array{subject: string, html: string}
     */
    public static function confirmation(array $order, array $items, string $sign, string $taxName, string $siteName): array
    {
        return self::compose(
            order: $order,
            items: $items,
            sign: $sign,
            taxName: $taxName,
            siteName: $siteName,
            subject: 'Your ' . $siteName . ' trade order ' . $order['reference'],
            intro: 'Thank you for your order. We have received it and will confirm it shortly.',
        );
    }

    /**
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $items
     * @return array{subject: string, html: string}
     */
    public static function dispatched(array $order, array $items, string $sign, string $taxName, string $siteName): array
    {
        return self::compose(
            order: $order,
            items: $items,
            sign: $sign,
            taxName: $taxName,
            siteName: $siteName,
            subject: 'Your ' . $siteName . ' trade order ' . $order['reference'] . ' has been dispatched',
            intro: 'Your order has been dispatched. An invoice is available from your trade account.',
        );
    }

    /** Whoever the email is for: the contact, else the company, else "there" */
    public static function greetingName(array $order): string
    {
        $contact = \trim((string) ($order['contact_name'] ?? ''));
        if ($contact !== '') return $contact;

        return \trim((string) ($order['company_name'] ?? '')) ?: 'there';
    }

    /**
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $items
     * @return array{subject: string, html: string}
     */
    private static function compose(array $order, array $items, string $sign, string $taxName, string $siteName, string $subject, string $intro): array
    {
        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr><td style="padding:6px 8px;border-bottom:1px solid #ddd">' . self::e((string) $item['product_title'])
                . ($item['product_ref'] !== '' ? '<br><span style="color:#777;font-size:13px">' . self::e((string) $item['product_ref']) . '</span>' : '')
                . '</td><td style="padding:6px 8px;border-bottom:1px solid #ddd;text-align:center">' . (int) $item['quantity']
                . '</td><td style="padding:6px 8px;border-bottom:1px solid #ddd;text-align:right">' . self::money($sign, $item['line_total_exc_tax']) . '</td></tr>';
        }

        $rate = ($order['tax_rate_percent'] ?? null) === null ? null : (float) $order['tax_rate_percent'];
        $facts = [['Order', (string) $order['reference']]];
        $hasPo = !empty($order['po_reference']);
        if ($hasPo) $facts[] = ['Your PO', (string) $order['po_reference']];
        $facts[] = ['Payment terms', TradeInvoiceLayout::paymentTerms($order['payment_terms'] ?? null)];

        $factsHtml = '';
        foreach ($facts as [$label, $value]) {
            $factsHtml .= '<tr><td style="padding:2px 12px 2px 0;color:#777">' . self::e($label) . '</td><td>' . self::e($value) . '</td></tr>';
        }

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#222;max-width:560px">'
            . '<h2 style="margin:0 0 12px">Hello ' . self::e(self::greetingName($order)) . '</h2>'
            . '<p>' . self::e($intro) . '</p>'
            . '<table style="border-collapse:collapse;margin:8px 0 16px">' . $factsHtml . '</table>'
            . '<table style="border-collapse:collapse;width:100%"><tr><th style="text-align:left;padding:6px 8px;border-bottom:2px solid #222">Product</th>'
            . '<th style="padding:6px 8px;border-bottom:2px solid #222">Qty</th><th style="text-align:right;padding:6px 8px;border-bottom:2px solid #222">Total (ex ' . self::e($taxName) . ')</th></tr>' . $rows . '</table>'
            . '<table style="border-collapse:collapse;margin:12px 0 0 auto">'
            . '<tr><td style="padding:2px 12px;text-align:right">Subtotal</td><td style="text-align:right">' . self::money($sign, $order['subtotal_exc_tax']) . '</td></tr>'
            . '<tr><td style="padding:2px 12px;text-align:right">' . self::e(TradeInvoiceLayout::taxLabel(taxName: $taxName, ratePercent: $rate)) . '</td><td style="text-align:right">' . self::money($sign, $order['tax_amount']) . '</td></tr>'
            . '<tr><td style="padding:2px 12px;text-align:right;font-weight:bold">Total</td><td style="text-align:right;font-weight:bold">' . self::money($sign, $order['total_inc_tax']) . '</td></tr></table>'
            . '<p style="color:#777;margin-top:24px">' . self::e($siteName) . '</p></div>';

        return ['subject' => $subject, 'html' => $html];
    }

    private static function money(string $sign, mixed $amount): string
    {
        return self::e($sign . \number_format((float) $amount, 2));
    }

    private static function e(string $text): string
    {
        return \htmlspecialchars($text, ENT_QUOTES);
    }
}
