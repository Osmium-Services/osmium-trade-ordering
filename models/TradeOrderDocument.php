<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/** Builds and sends an order's PDF (acknowledgement, confirmation or invoice by status). Used by the admin and by the customer's own download. */
final class TradeOrderDocument
{
    /** @param array<string, mixed> $order the order with the customer's details joined on (getForAdmin) */
    public static function hasDocument(array $order): bool
    {
        return TradeInvoiceLayout::document((string) $order['status']) !== null;
    }

    /** Streams the PDF and ends the request. @param array<string, mixed> $order */
    public static function send(object $osmium, array $order): never
    {
        $document = TradeInvoiceLayout::document((string) $order['status']);
        $config = $osmium->config;
        $taxName = (string) ($config->shop->taxName ?? 'VAT');
        $items = (new TradeOrderItem($osmium->dataSource))->listByOrder((int) $order['id']);
        $pdf = (new TradeInvoicePdf())->build(
            order: $order,
            items: $items,
            companyLines: TradeInvoiceLayout::companyLines(company: (array) ($config->company ?? []), taxName: $taxName),
            siteName: (string) ($config->site->name ?? ''),
            sign: (string) ($config->shop->currencySign ?? '£'),
            taxName: $taxName,
        );

        $filename = TradeInvoiceLayout::filename(prefix: $document['prefix'], reference: (string) $order['reference']);
        \header('Content-Type: application/pdf');
        \header('Content-Disposition: attachment; filename="' . $filename . '"');
        \header('Content-Length: ' . \strlen($pdf));
        \header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdf;
        exit;
    }
}
