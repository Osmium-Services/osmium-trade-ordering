<?php

declare(strict_types=1);

namespace Tests\Services\TradeOrdering;

use Osmium\Services\TradeOrdering\Models\TradeOrderingMcp;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/TradeOrderingMcp.php';

final class TradeOrderingMcpTest extends TestCase
{
    public function test_the_tool_is_read_only_and_takes_no_input(): void
    {
        $tools = TradeOrderingMcp::tools([]);

        $this->assertSame(['trade_orders_summary'], \array_column($tools, 'name'));
        $this->assertArrayNotHasKey('write', $tools[0]);
    }

    public function test_periods_are_bucketed_per_status_and_empty_ones_are_left_out(): void
    {
        $periods = TradeOrderingMcp::periods([
            ['status' => 'pending', 'today_count' => '1', 'today_value' => '120.00', 'week_count' => '2', 'week_value' => '300.50', 'month_count' => '5', 'month_value' => '900.00'],
            ['status' => 'shipped', 'today_count' => '0', 'today_value' => '0', 'week_count' => '0', 'week_value' => '0', 'month_count' => '1', 'month_value' => '75.00'],
        ]);

        $this->assertSame(['pending' => ['count' => 1, 'value' => 120.0]], $periods['today']);
        $this->assertSame(300.5, $periods['last_7_days']['pending']['value']);
        $this->assertArrayNotHasKey('shipped', $periods['last_7_days']);
        $this->assertSame(['count' => 1, 'value' => 75.0], $periods['last_30_days']['shipped']);
    }

    public function test_the_queue_reports_the_oldest_pending_wait_or_null(): void
    {
        $waiting = TradeOrderingMcp::shape([], ['open_orders' => '4', 'pending_orders' => '2', 'oldest_pending_days' => '3'], ['total' => 0, 'orders' => 0], []);
        $none = TradeOrderingMcp::shape([], ['open_orders' => '0', 'pending_orders' => '0', 'oldest_pending_days' => null], ['total' => 0, 'orders' => 0], []);

        $this->assertSame(['open_orders' => 4, 'pending_orders' => 2, 'oldest_pending_days' => 3], $waiting['queue']);
        $this->assertNull($none['queue']['oldest_pending_days']);
        $this->assertSame(0, TradeOrderingMcp::shape([], [], ['total' => 0, 'orders' => 0], [])['queue']['open_orders']);
    }

    public function test_the_newest_orders_carry_no_customer_or_po_data(): void
    {
        $out = TradeOrderingMcp::shape([], [], ['total' => 250.0, 'orders' => 3], [[
            'reference' => 'TR-0007', 'status' => 'pending', 'item_count' => '3', 'total_inc_tax' => '250.00', 'payment_terms' => '30 days', 'submitted_at' => '2026-11-20 09:00:00',
            'company_name' => 'Secret Ltd', 'email' => 'sam@secret.example', 'po_reference' => 'PO-SECRET-1', 'customer_id' => '9',
        ]]);
        $json = \json_encode($out);

        foreach (['Secret', 'sam@', 'PO-SECRET', 'customer_id'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $this->assertSame(['reference', 'status', 'items', 'total_inc_tax', 'payment_terms', 'submitted_at'], \array_keys($out['newest'][0]));
        $this->assertSame(250.0, $out['this_month']['value_inc_tax']);
    }
}
