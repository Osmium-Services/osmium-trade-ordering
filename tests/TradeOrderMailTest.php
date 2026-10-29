<?php

declare(strict_types=1);

namespace Tests\Services\TradeOrdering;

use Osmium\Services\TradeOrdering\Models\TradeInvoiceLayout;
use Osmium\Services\TradeOrdering\Models\TradeOrderEmailSamples;
use Osmium\Services\TradeOrdering\Models\TradeOrderMail;
use Osmium\Services\TradeOrdering\Models\TradeOrderStatus;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/TradeOrderStatus.php';
require_once __DIR__ . '/../models/TradeInvoiceLayout.php';
require_once __DIR__ . '/../models/TradeOrderMail.php';
require_once __DIR__ . '/../models/TradeOrderEmailSamples.php';

final class TradeOrderMailTest extends TestCase
{
    public function test_the_confirmation_greets_the_contact_and_lists_the_order_and_totals(): void
    {
        $sample = TradeOrderEmailSamples::data();

        $mail = TradeOrderMail::confirmation(order: $sample['order'], items: $sample['items'], sign: '£', taxName: 'VAT', siteName: 'Solar Co');

        $this->assertSame('Your Solar Co trade order TO-SAMPLE-0001', $mail['subject']);
        $this->assertStringContainsString('Hello Jane Doe', $mail['html']);
        $this->assertStringContainsString('We have received it', $mail['html']);
        $this->assertStringContainsString('PO-1234', $mail['html']);
        $this->assertStringContainsString('Company', $mail['html']);
        $this->assertStringContainsString('Sample Trade Ltd', $mail['html']);
        $this->assertStringContainsString('Example Solar Panel 200W', $mail['html']);
        $this->assertStringContainsString('VAT (20%)', $mail['html']);
        $this->assertStringContainsString('£315.00', $mail['html']);
    }

    public function test_the_dispatch_email_says_so_and_falls_back_to_30_days_and_the_company_name(): void
    {
        $sample = TradeOrderEmailSamples::data();
        $sample['order']['payment_terms'] = null;
        $sample['order']['contact_name'] = '';
        $sample['order']['po_reference'] = null;
        $sample['order']['tax_rate_percent'] = null;

        $mail = TradeOrderMail::dispatched(order: $sample['order'], items: $sample['items'], sign: '£', taxName: 'VAT', siteName: 'Solar Co');

        $this->assertStringEndsWith('has been dispatched', $mail['subject']);
        $this->assertStringContainsString('Hello Sample Trade Ltd', $mail['html']);
        $this->assertStringContainsString('30 Days', $mail['html']);
        $this->assertStringNotContainsString('Your PO', $mail['html']);

        $sample['order']['company_name'] = '';
        $bare = TradeOrderMail::dispatched(order: $sample['order'], items: $sample['items'], sign: '£', taxName: 'VAT', siteName: 'Solar Co');
        $this->assertStringNotContainsString('>Company<', $bare['html']);
        $this->assertStringNotContainsString('VAT (', $mail['html']);
    }

    public function test_customer_text_is_escaped(): void
    {
        $sample = TradeOrderEmailSamples::data();
        $sample['order']['contact_name'] = '<script>x</script>';
        $sample['items'][0]['product_title'] = 'A & <b>B</b>';

        $mail = TradeOrderMail::confirmation(order: $sample['order'], items: $sample['items'], sign: '£', taxName: 'VAT', siteName: 'Solar Co');

        $this->assertStringNotContainsString('<script>', $mail['html']);
        $this->assertStringContainsString('A &amp; &lt;b&gt;B&lt;/b&gt;', $mail['html']);
    }

    public function test_both_emails_are_offered_for_the_test_list_with_usable_ids_and_handlers(): void
    {
        $samples = TradeOrderEmailSamples::samples([]);

        $this->assertSame(['trade_ordering.confirmation', 'trade_ordering.dispatched'], \array_column($samples, 'id'));
        foreach ($samples as $sample) {
            $this->assertMatchesRegularExpression('/^[a-z0-9_.-]+$/', $sample['id']);
            $this->assertSame('Trade Ordering', $sample['group']);
            $this->assertTrue(\is_callable(\explode('::', $sample['handler'])), $sample['handler'] . ' is not callable');
        }
    }

    public function test_the_emails_use_the_site_email_styling_with_the_logo_when_there_is_one(): void
    {
        $sample = TradeOrderEmailSamples::data();
        $brand = ['logoUrl' => 'https://x.test/logo.png', 'logoDarkCss' => '<style>.d{}</style>', 'logoDarkImg' => '<img class="logo-dark" src="https://x.test/d.png">'];

        $mail = TradeOrderMail::confirmation(order: $sample['order'], items: $sample['items'], sign: '£', taxName: 'VAT', siteName: 'Solar Co', brand: $brand);

        $this->assertStringContainsString('class="logo-band"', $mail['html']);
        $this->assertStringContainsString('src="https://x.test/logo.png"', $mail['html']);
        $this->assertStringContainsString('class="logo-dark"', $mail['html']);
        $this->assertStringContainsString('border-bottom:4px solid #166534', $mail['html']);
        $this->assertStringContainsString('&copy; Solar Co - This is an automated message', $mail['html']);
    }

    public function test_without_a_logo_the_site_name_stands_in_the_header(): void
    {
        $sample = TradeOrderEmailSamples::data();

        $mail = TradeOrderMail::dispatched(order: $sample['order'], items: $sample['items'], sign: '£', taxName: 'VAT', siteName: 'Solar Co');

        $this->assertStringNotContainsString('<img', $mail['html']);
        $this->assertStringContainsString('font-weight:bold;color:#166534">Solar Co</span>', $mail['html']);
    }
}
