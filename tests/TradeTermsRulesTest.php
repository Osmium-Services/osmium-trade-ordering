<?php

declare(strict_types=1);

namespace Tests\Services\TradeOrdering;

use Osmium\Services\TradeOrdering\Models\TradeTermsRules;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/TradeTermsRules.php';

final class TradeTermsRulesTest extends TestCase
{
    private function posted(string $discount, string $terms): array
    {
        return [TradeTermsRules::DISCOUNT => $discount, TradeTermsRules::PAYMENT => $terms];
    }

    public function test_the_form_gets_a_discount_number_and_a_payment_terms_dropdown(): void
    {
        $fields = TradeTermsRules::fields();

        $this->assertSame(['ordering_discount_percent', 'ordering_payment_terms'], \array_column($fields, 'name'));
        $this->assertSame('number', $fields[0]['type']);
        $this->assertSame('select', $fields[1]['type']);
        $this->assertSame(['', 'COD', '7 Days', '14 Days', '30 Days', '60 Days'], \array_keys($fields[1]['options']));
    }

    public function test_saved_rows_show_as_clean_form_values(): void
    {
        $values = TradeTermsRules::values([
            ['customer_id' => '7', 'discount_percent' => '12.50', 'payment_terms' => '30 Days'],
            ['customer_id' => '8', 'discount_percent' => '10.00', 'payment_terms' => null],
            ['customer_id' => '9', 'discount_percent' => '0.00', 'payment_terms' => null],
        ]);

        $this->assertSame(['ordering_discount_percent' => '12.5', 'ordering_payment_terms' => '30 Days'], $values[7]);
        $this->assertSame(['ordering_discount_percent' => '10', 'ordering_payment_terms' => ''], $values[8]);
        $this->assertSame('0', $values[9]['ordering_discount_percent']);
    }

    public function test_good_terms_pass_and_bad_ones_name_the_problem(): void
    {
        $this->assertNull(TradeTermsRules::problem($this->posted('12.5', '30 Days')));
        $this->assertNull(TradeTermsRules::problem($this->posted('', '')));
        $this->assertNull(TradeTermsRules::problem($this->posted('0', 'COD')));
        $this->assertNull(TradeTermsRules::problem($this->posted('100', '')));
        $this->assertNotNull(TradeTermsRules::problem($this->posted('abc', '')));
        $this->assertNotNull(TradeTermsRules::problem($this->posted('-1', '')));
        $this->assertNotNull(TradeTermsRules::problem($this->posted('100.5', '')));
        $this->assertNotNull(TradeTermsRules::problem($this->posted('10', '90 Days')));
    }

    public function test_clean_gives_a_rounded_discount_and_null_for_no_terms(): void
    {
        $this->assertSame(['discount_percent' => 12.35, 'payment_terms' => null], TradeTermsRules::clean($this->posted(' 12.345 ', '')));
        $this->assertSame(['discount_percent' => 0.0, 'payment_terms' => '7 Days'], TradeTermsRules::clean($this->posted('', '7 Days')));
    }
}
