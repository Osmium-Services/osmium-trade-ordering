<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/** Which brands get the trade discount. Pure. */
final class TradeBrandRules
{
    /**
     * The brands as the settings page lists them.
     *
     * @param array<int, array{id: int, name: string}> $brands The shop's live brands
     * @param int[] $excludedIds
     * @return array<int, array{id: int, name: string, eligible: bool}>
     */
    public static function rows(array $brands, array $excludedIds): array
    {
        $excluded = \array_flip($excludedIds);

        return \array_map(static fn(array $brand): array => ['id' => (int) $brand['id'], 'name' => (string) $brand['name'], 'eligible' => !isset($excluded[(int) $brand['id']])], $brands);
    }

    /**
     * The exclusions after a save. A brand the shop still lists is excluded when its box is not ticked; an exclusion
     * for a brand the shop no longer lists (deleted, maybe restored later) is kept as it was.
     *
     * @param array<int, array{id: int, name: string}> $brands The shop's live brands
     * @param int[] $currentExcluded
     * @param int[] $tickedIds Brands whose "gets the discount" box was ticked
     * @return int[] sorted brand ids
     */
    public static function nextExclusions(array $brands, array $currentExcluded, array $tickedIds): array
    {
        $liveIds = \array_map(static fn(array $brand): int => (int) $brand['id'], $brands);
        $ticked = \array_flip(\array_map('intval', $tickedIds));

        $keptUnknown = \array_diff($currentExcluded, $liveIds);
        $unticked = \array_filter($liveIds, static fn(int $id): bool => !isset($ticked[$id]));

        $next = \array_values(\array_unique(\array_merge($keptUnknown, $unticked)));
        \sort($next);

        return $next;
    }
}
