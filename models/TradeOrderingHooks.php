<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

use Osmium\Core\Library\OsmiumPDO;

/** Trade Ordering's answers to Trade Accounts' extra customer field hooks (trade.customer.fields...). */
final class TradeOrderingHooks
{
    /**
     * @param array{customerIds?: int[], dataSource?: OsmiumPDO} $payload
     * @return array{fields: array<int, array<string, mixed>>, values: array<int, array<string, string>>}
     */
    public static function fields(array $payload): array
    {
        $db = $payload['dataSource'] ?? null;
        $rows = $db instanceof OsmiumPDO ? (new TradeCustomerTerms($db))->getMany((array) ($payload['customerIds'] ?? [])) : [];

        return ['fields' => TradeTermsRules::fields(), 'values' => TradeTermsRules::values($rows)];
    }

    /** @param array{values?: array<string, string>} $payload @return string|null */
    public static function validate(array $payload): ?string
    {
        return TradeTermsRules::problem((array) ($payload['values'] ?? []));
    }

    /** @param array{customerId?: int, values?: array<string, string>, dataSource?: OsmiumPDO} $payload */
    public static function save(array $payload): void
    {
        $db = $payload['dataSource'] ?? null;
        $values = (array) ($payload['values'] ?? []);
        $nothingToSave = !($db instanceof OsmiumPDO) || !isset($values[TradeTermsRules::DISCOUNT], $values[TradeTermsRules::PAYMENT]);
        if ($nothingToSave) return;

        (new TradeCustomerTerms($db))->save(customerId: (int) $payload['customerId'], terms: TradeTermsRules::clean($values));
    }
}
