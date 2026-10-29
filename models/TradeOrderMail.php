<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/** The two emails a trade customer gets about an order: it was received, and it was dispatched. Pure. */
final class TradeOrderMail
{
    /**
     * @param array<string, mixed> $order with the customer's name and email joined on
     * @param array<int, array<string, mixed>> $items
     * @param array{logoUrl?: string, logoDarkCss?: string, logoDarkImg?: string} $brand the site's email logo, as BrandLogo::emailVars() gives it
     * @return array{subject: string, html: string}
     */
    public static function confirmation(array $order, array $items, string $sign, string $taxName, string $siteName, array $brand = []): array
    {
        return self::compose(
            order: $order,
            items: $items,
            sign: $sign,
            taxName: $taxName,
            siteName: $siteName,
            brand: $brand,
            subject: 'Your ' . $siteName . ' trade order ' . $order['reference'],
            intro: 'Thank you for your order. We have received it and will confirm it shortly.',
        );
    }

    /**
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $items
     * @return array{subject: string, html: string}
     */
    public static function dispatched(array $order, array $items, string $sign, string $taxName, string $siteName, array $brand = []): array
    {
        return self::compose(
            order: $order,
            items: $items,
            sign: $sign,
            taxName: $taxName,
            siteName: $siteName,
            brand: $brand,
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
    private static function compose(array $order, array $items, string $sign, string $taxName, string $siteName, array $brand, string $subject, string $intro): array
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

        $inner = '<h2 style="color:#166534;margin:0 0 20px 0;font-size:22px">Hello ' . self::e(self::greetingName($order)) . '</h2>'
            . '<p style="color:#666;font-size:16px;line-height:1.5;margin:0 0 20px 0">' . self::e($intro) . '</p>'
            . '<table style="border-collapse:collapse;margin:8px 0 20px;color:#666;font-size:15px">' . $factsHtml . '</table>'
            . '<table style="border-collapse:collapse;width:100%;color:#444;font-size:15px"><tr><th style="text-align:left;padding:6px 8px;border-bottom:2px solid #166534">Product</th>'
            . '<th style="padding:6px 8px;border-bottom:2px solid #166534">Qty</th><th style="text-align:right;padding:6px 8px;border-bottom:2px solid #166534">Total (ex ' . self::e($taxName) . ')</th></tr>' . $rows . '</table>'
            . '<table style="border-collapse:collapse;margin:12px 0 0 auto;color:#444;font-size:15px">'
            . '<tr><td style="padding:2px 12px;text-align:right">Subtotal</td><td style="text-align:right">' . self::money($sign, $order['subtotal_exc_tax']) . '</td></tr>'
            . '<tr><td style="padding:2px 12px;text-align:right">' . self::e(TradeInvoiceLayout::taxLabel(taxName: $taxName, ratePercent: $rate)) . '</td><td style="text-align:right">' . self::money($sign, $order['tax_amount']) . '</td></tr>'
            . '<tr><td style="padding:2px 12px;text-align:right;font-weight:bold;color:#166534">Total</td><td style="text-align:right;font-weight:bold;color:#166534">' . self::money($sign, $order['total_inc_tax']) . '</td></tr></table>';

        $html = self::layout(inner: $inner, siteName: $siteName, brand: $brand);

        return ['subject' => $subject, 'html' => $html];
    }

    /**
     * The site's email styling, as the shop and core emails have it: logo band, grey page, grey footer.
     *
     * @param array{logoUrl?: string, logoDarkCss?: string, logoDarkImg?: string} $brand
     */
    private static function layout(string $inner, string $siteName, array $brand): string
    {
        $site = self::e($siteName);
        $logoUrl = (string) ($brand['logoUrl'] ?? '');
        $logo = $logoUrl === ''
            ? '<span style="font-size:22px;font-weight:bold;color:#166534">' . $site . '</span>'
            : '<img class="logo-light" src="' . self::e($logoUrl) . '" alt="' . $site . '" style="max-width:220px;height:auto">' . ($brand['logoDarkImg'] ?? '');

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">' . ($brand['logoDarkCss'] ?? '') . '</head>'
            . '<body style="margin:0;padding:0;font-family:Arial,sans-serif;background-color:#f4f4f4">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background-color:#ffffff">'
            . '<tr><td class="logo-band" style="padding:30px;text-align:center;background-color:#ffffff;border-bottom:4px solid #166534">' . $logo . '</td></tr>'
            . '<tr><td style="padding:40px 30px">' . $inner . '</td></tr>'
            . '<tr><td style="padding:20px 30px;background-color:#f8f8f8;text-align:center"><p style="color:#999;font-size:12px;margin:0">&copy; ' . $site . ' - This is an automated message, please do not reply.</p></td></tr>'
            . '</table></body></html>';
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
