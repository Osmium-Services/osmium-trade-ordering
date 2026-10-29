<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

require_once __DIR__ . '/../../../../vendor/tcpdf/tcpdf.php';

/**
 * Draws a trade order document (acknowledgement, confirmation or invoice) with the TCPDF that ships with the site.
 * Trade Ordering's own layout: PO reference, payment terms and discount sit with the invoice details.
 */
final class TradeInvoicePdf
{
    private const MARGIN = 15;

    /**
     * @param array<string, mixed> $order the order with the customer's details joined on
     * @param array<int, array<string, mixed>> $items
     * @param string[] $companyLines
     */
    public function build(array $order, array $items, array $companyLines, string $siteName, string $sign, string $taxName): string
    {
        $document = TradeInvoiceLayout::document((string) $order['status']);
        if ($document === null) throw new \InvalidArgumentException('This order has no document');

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator($siteName);
        $pdf->SetAuthor($siteName);
        $pdf->SetTitle($document['title']);
        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $this->header(pdf: $pdf, title: $document['title'], siteName: $siteName, companyLines: $companyLines);
        $this->details(pdf: $pdf, order: $order);
        $this->table(pdf: $pdf, items: $items, sign: $sign);
        $this->totals(pdf: $pdf, order: $order, sign: $sign, taxName: $taxName);
        $this->footer(pdf: $pdf, text: $document['footer']);

        return $pdf->Output('', 'S');
    }

    /** @param string[] $companyLines */
    private function header(\TCPDF $pdf, string $title, string $siteName, array $companyLines): void
    {
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(110, 10, $title, 0, 0);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(70, 6, $siteName, 0, 1, 'R');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(100, 100, 100);
        foreach ($companyLines as $line) {
            $pdf->SetX(110);
            $pdf->Cell(85, 4, $line, 0, 1, 'R');
        }
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetY(\max($pdf->GetY(), 45) + 8);
    }

    /** @param array<string, mixed> $order */
    private function details(\TCPDF $pdf, array $order): void
    {
        $top = $pdf->GetY();
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(90, 6, 'BILL TO:', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $billTo = TradeInvoiceLayout::billToLines($order);
        foreach ($billTo as $line) {
            $pdf->Cell(90, 5, $line, 0, 1);
        }
        $leftBottom = $pdf->GetY();

        $date = $order['submitted_at'] ? \date('j M Y', \strtotime((string) $order['submitted_at'])) : \date('j M Y');
        $rows = [['Order #:', (string) $order['reference']]];
        $hasPo = !empty($order['po_reference']);
        if ($hasPo) $rows[] = ['PO Reference:', (string) $order['po_reference']];
        $rows[] = ['Date:', $date];
        $rows[] = ['Payment Terms:', TradeInvoiceLayout::paymentTerms($order['payment_terms'] ?? null)];
        $rows[] = ['Discount:', TradeInvoiceLayout::discountText((float) $order['discount_percent'])];

        $pdf->SetXY(110, $top);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(85, 6, 'DETAILS:', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        foreach ($rows as [$label, $value]) {
            $pdf->SetX(110);
            $pdf->Cell(35, 5, $label);
            $pdf->Cell(50, 5, $value, 0, 1);
        }

        $pdf->SetY(\max($leftBottom, $pdf->GetY()) + 8);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function table(\TCPDF $pdf, array $items, string $sign): void
    {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(40, 8, 'Ref', 1, 0, 'L');
        $pdf->Cell(65, 8, 'Product', 1, 0, 'L');
        $pdf->Cell(15, 8, 'Qty', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Price', 1, 0, 'R');
        $pdf->Cell(30, 8, 'Total', 1, 1, 'R');

        $pdf->SetFont('helvetica', '', 9);
        foreach ($items as $item) {
            $pdf->Cell(40, 7, \mb_strimwidth((string) $item['product_ref'], 0, 22, '...'), 1);
            $pdf->Cell(65, 7, \mb_strimwidth((string) $item['product_title'], 0, 38, '...'), 1);
            $pdf->Cell(15, 7, (string) $item['quantity'], 1, 0, 'C');
            $pdf->Cell(30, 7, $sign . \number_format((float) $item['unit_price_exc_tax'], 2), 1, 0, 'R');
            $pdf->Cell(30, 7, $sign . \number_format((float) $item['line_total_exc_tax'], 2), 1, 1, 'R');
        }
    }

    /** @param array<string, mixed> $order */
    private function totals(\TCPDF $pdf, array $order, string $sign, string $taxName): void
    {
        $rate = $order['tax_rate_percent'] === null ? null : (float) $order['tax_rate_percent'];
        $pdf->SetY($pdf->GetY() + 6);
        $pdf->SetFont('helvetica', '', 10);

        $pdf->SetX(110);
        $pdf->Cell(50, 6, 'Subtotal (ex ' . $taxName . '):', 0, 0, 'R');
        $pdf->Cell(30, 6, $sign . \number_format((float) $order['subtotal_exc_tax'], 2), 0, 1, 'R');

        $pdf->SetX(110);
        $pdf->Cell(50, 6, TradeInvoiceLayout::taxLabel(taxName: $taxName, ratePercent: $rate) . ':', 0, 0, 'R');
        $pdf->Cell(30, 6, $sign . \number_format((float) $order['tax_amount'], 2), 0, 1, 'R');

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetX(110);
        $pdf->Cell(50, 8, 'TOTAL:', 0, 0, 'R');
        $pdf->Cell(30, 8, $sign . \number_format((float) $order['total_inc_tax'], 2), 0, 1, 'R');
    }

    private function footer(\TCPDF $pdf, string $text): void
    {
        $pdf->SetAutoPageBreak(false); // the footer sits below the break line, so it must not start a new page
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY(self::MARGIN, 275);
        $pdf->Cell(180, 5, $text, 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
    }
}
