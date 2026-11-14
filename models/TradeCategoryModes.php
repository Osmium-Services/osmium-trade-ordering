<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/** The three ways the trade price list can treat a shop category. Pure. */
final class TradeCategoryModes
{
    public const SECTION = 'section'; // Its own section (the default, never stored)
    public const INSIDE = 'inside';   // Shown inside its parent's section
    public const HIDDEN = 'hidden';   // Its products are left off the list

    public const LABELS = [
        self::SECTION => 'Its own section',
        self::INSIDE => 'Inside its parent',
        self::HIDDEN => 'Hidden',
    ];

    /**
     * The categories as the settings page lists them, in tree order.
     *
     * @param array<int, array{id: int, parentId: ?int, name: string, path: string[]}> $categories The shop's categories
     * @param array<int, string> $modes category id => stored mode
     * @return array<int, array{id: int, name: string, depth: int, mode: string, canBeInside: bool}>
     */
    public static function rows(array $categories, array $modes): array
    {
        return \array_map(static fn(array $category): array => [
            'id' => (int) $category['id'],
            'name' => (string) $category['name'],
            'depth' => \count($category['path']) - 1,
            'mode' => $modes[(int) $category['id']] ?? self::SECTION,
            'canBeInside' => $category['parentId'] !== null,
        ], $categories);
    }

    /**
     * The stored modes after a save. A category the shop still lists takes the posted mode (an unknown or missing one
     * means its own section); a rule for a category the shop no longer lists is kept as it was.
     *
     * @param array<int, array{id: int, parentId: ?int}> $categories
     * @param array<int, string> $current
     * @param array<int|string, mixed> $posted category id => mode
     * @return array<int, string> only the categories that are not their own section
     */
    public static function next(array $categories, array $current, array $posted): array
    {
        $live = [];
        foreach ($categories as $category) {
            $live[(int) $category['id']] = $category['parentId'];
        }

        $next = \array_diff_key($current, $live);
        foreach ($live as $id => $parentId) {
            $mode = (string) ($posted[$id] ?? self::SECTION);
            $valid = \in_array($mode, [self::INSIDE, self::HIDDEN], true) && !($mode === self::INSIDE && $parentId === null);
            if ($valid) $next[$id] = $mode;
        }
        \ksort($next);

        return $next;
    }
}
