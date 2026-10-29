<?php

declare(strict_types=1);

namespace Tests\Services\TradeOrdering;

use Osmium\Services\TradeOrdering\Models\TradeInvoiceLayout;
use Osmium\Services\TradeOrdering\Models\TradeOrderStatus;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/TradeOrderStatus.php';
require_once __DIR__ . '/../models/TradeInvoiceLayout.php';

final class TradeInvoiceLayoutTest extends TestCase
{
    public function test_the_document_follows_the_order_status_and_drafts_and_cancelled_have_none(): void
    {
        $this->assertSame('ORDER ACKNOWLEDGEMENT', TradeInvoiceLayout::document('pending')['title']);
        $this->assertSame('ORDER CONFIRMATION', TradeInvoiceLayout::document('processing')['title']);
        $this->assertSame('INVOICE', TradeInvoiceLayout::document('delivered')['title']);
        $this->assertSame('Invoice', TradeInvoiceLayout::document('shipped')['prefix']);
        $this->assertNull(TradeInvoiceLayout::document('draft'));
        $this->assertFalse(TradeInvoiceLayout::hasDocument('cancelled'));
        foreach (TradeOrderStatus::OPEN as $status) {
            $this->assertTrue(TradeInvoiceLayout::hasDocument($status));
        }
    }

    public function test_the_filename_keeps_only_safe_characters(): void
    {
        $this->assertSame('Invoice-TO-2026-0007.pdf', TradeInvoiceLayout::filename('Invoice', 'TO-2026-0007'));
        $this->assertSame('OrderAck-TO-20260007x.pdf', TradeInvoiceLayout::filename('OrderAck', 'TO-2026/0007 "x"'));
    }

    public function test_an_order_with_no_terms_prints_30_days(): void
    {
        $this->assertSame('30 Days', TradeInvoiceLayout::paymentTerms(null));
        $this->assertSame('30 Days', TradeInvoiceLayout::paymentTerms('  '));
        $this->assertSame('COD', TradeInvoiceLayout::paymentTerms(' COD '));
    }

    public function test_discount_and_tax_wording(): void
    {
        $this->assertSame('None', TradeInvoiceLayout::discountText(0.0));
        $this->assertSame('12.5%', TradeInvoiceLayout::discountText(12.5));
        $this->assertSame('10%', TradeInvoiceLayout::discountText(10.0));
        $this->assertSame('VAT (20%)', TradeInvoiceLayout::taxLabel('VAT', 20.0));
        $this->assertSame('VAT (5.5%)', TradeInvoiceLayout::taxLabel('VAT', 5.5));
        $this->assertSame('VAT', TradeInvoiceLayout::taxLabel('VAT', null));
    }

    public function test_company_and_bill_to_lines_skip_what_is_empty(): void
    {
        $company = ['address' => '1 High St, Leeds, LS1 1AA', 'phone' => '0113', 'email' => 'a@b.co', 'companyNumber' => '', 'vatNumber' => 'GB123'];
        $this->assertSame(['1 High St', 'Leeds', 'LS1 1AA', 'Tel: 0113', 'Email: a@b.co', 'VAT Reg: GB123'], TradeInvoiceLayout::companyLines($company, 'VAT'));

        $order = ['company_name' => 'Acme', 'contact_name' => '', 'address_line_1' => '2 Low Rd', 'address_line_2' => null, 'city' => 'York', 'county' => '', 'postcode' => 'YO1', 'country' => 'United Kingdom', 'email' => 'x@acme.co'];
        $this->assertSame(['Acme', '2 Low Rd', 'York', 'YO1', 'x@acme.co'], TradeInvoiceLayout::billToLines($order));
        $this->assertContains('Ireland', TradeInvoiceLayout::billToLines(['company_name' => 'A', 'country' => 'Ireland']));
    }
}
