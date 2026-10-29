<?php

declare(strict_types=1);

namespace Tests\Services\TradeOrdering;

use Osmium\Services\TradeOrdering\Models\TradeBrandRules;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/TradeBrandRules.php';

final class TradeBrandRulesTest extends TestCase
{
    private const BRANDS = [['id' => 1, 'name' => 'Acme'], ['id' => 2, 'name' => 'Zed'], ['id' => 3, 'name' => 'Mid']];

    public function test_rows_mark_brands_that_are_not_excluded_as_eligible(): void
    {
        $rows = TradeBrandRules::rows(self::BRANDS, [2]);

        $this->assertSame([true, false, true], \array_column($rows, 'eligible'));
        $this->assertSame(['Acme', 'Zed', 'Mid'], \array_column($rows, 'name'));
    }

    public function test_an_unticked_live_brand_becomes_excluded_and_a_ticked_one_is_not(): void
    {
        $this->assertSame([2], TradeBrandRules::nextExclusions(self::BRANDS, [], [1, 3]));
        $this->assertSame([], TradeBrandRules::nextExclusions(self::BRANDS, [2], [1, 2, 3]));
        $this->assertSame([1, 2, 3], TradeBrandRules::nextExclusions(self::BRANDS, [], []));
    }

    public function test_an_exclusion_for_a_brand_the_shop_no_longer_lists_is_kept(): void
    {
        $this->assertSame([2, 9], TradeBrandRules::nextExclusions(self::BRANDS, [9], [1, 3]));
        $this->assertSame([9], TradeBrandRules::nextExclusions(self::BRANDS, [9], [1, 2, 3]));
    }

    public function test_ticked_ids_may_arrive_as_strings_and_the_result_has_no_duplicates(): void
    {
        $this->assertSame([2], TradeBrandRules::nextExclusions(self::BRANDS, [2], ['1', '3']));
    }
}
