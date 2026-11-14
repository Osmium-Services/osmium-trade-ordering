<?php

declare(strict_types=1);

namespace Tests\Services\TradeOrdering;

use Osmium\Services\TradeOrdering\Models\TradeCartPayload;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/TradeCartPayload.php';

final class TradeCartPayloadTest extends TestCase
{
    public function test_no_order_is_an_empty_basket(): void
    {
        $cart = TradeCartPayload::build(null, [], '£');

        $this->assertSame(0, $cart['item_count']);
        $this->assertSame([], $cart['items']);
        $this->assertSame('0.00', $cart['total']);
    }

    public function test_a_line_shows_its_prices_with_the_currency_sign_and_its_options(): void
    {
        $order = ['item_count' => 2, 'subtotal_exc_tax' => '174.00', 'tax_amount' => '34.80', 'total_inc_tax' => '208.80'];
        $items = [['id' => '3', 'product_id' => '17', 'product_title' => 'Panel (Units: Pack)', 'product_ref' => 'SP', 'quantity' => '2', 'unit_price_exc_tax' => '87.00', 'line_total_exc_tax' => '174.00', 'options_display' => 'Units: Pack']];

        $cart = TradeCartPayload::build($order, $items, '£');

        $this->assertSame('£87.00', $cart['items'][0]['unit_price']);
        $this->assertSame('£174.00', $cart['items'][0]['line_total']);
        $this->assertSame('Units: Pack', $cart['items'][0]['options_display']);
        $this->assertSame('208.80', $cart['total']);
        $this->assertSame(2, $cart['item_count']);
    }
}
