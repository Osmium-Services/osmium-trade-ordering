<?php

declare(strict_types=1);

namespace Tests\Services\TradeOrdering;

use Osmium\Services\TradeOrdering\Models\TradeCategoryModes;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/TradeCategoryModes.php';

final class TradeCategoryModesTest extends TestCase
{
    private const CATEGORIES = [
        ['id' => 1, 'parentId' => null, 'name' => 'Panels', 'path' => ['Panels']],
        ['id' => 2, 'parentId' => 1, 'name' => 'Rigid', 'path' => ['Panels', 'Rigid']],
    ];

    public function test_rows_show_each_category_with_its_depth_and_mode_and_only_a_child_can_be_inside(): void
    {
        $rows = TradeCategoryModes::rows(self::CATEGORIES, [2 => TradeCategoryModes::INSIDE]);

        $this->assertSame(0, $rows[0]['depth']);
        $this->assertSame(TradeCategoryModes::SECTION, $rows[0]['mode']);
        $this->assertFalse($rows[0]['canBeInside']);
        $this->assertSame(1, $rows[1]['depth']);
        $this->assertSame(TradeCategoryModes::INSIDE, $rows[1]['mode']);
        $this->assertTrue($rows[1]['canBeInside']);
    }

    public function test_a_save_stores_only_inside_and_hidden_and_ignores_nonsense(): void
    {
        $next = TradeCategoryModes::next(self::CATEGORIES, [], [1 => 'hidden', 2 => 'inside', 99 => 'hidden']);
        $this->assertSame([1 => 'hidden', 2 => 'inside'], $next);

        $bad = TradeCategoryModes::next(self::CATEGORIES, [], [1 => 'inside', 2 => 'banana']);
        $this->assertSame([], $bad);
    }

    public function test_a_rule_for_a_category_the_shop_no_longer_lists_is_kept(): void
    {
        $next = TradeCategoryModes::next(self::CATEGORIES, [50 => 'hidden', 2 => 'hidden'], [2 => 'section']);

        $this->assertSame([50 => 'hidden'], $next);
    }
}
