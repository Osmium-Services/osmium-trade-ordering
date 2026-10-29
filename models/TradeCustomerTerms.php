<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/** Trade customers' discount and payment terms, one row per customer. */
class TradeCustomerTerms extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'trade_customer_terms');
    }

    /**
     * @param int[] $customerIds
     * @return array<int, array<string, mixed>>
     */
    public function getMany(array $customerIds): array
    {
        $none = $customerIds === [];
        if ($none) return [];

        $bindings = [':ids' => \implode(',', \array_map('intval', $customerIds))];

        return $this->database->prepare(sql: $this->loadSqlFile('terms-get-many.sql'), bindings: $bindings)->resultset();
    }

    /** @param array{discount_percent: float, payment_terms: string|null} $terms */
    public function save(int $customerId, array $terms): void
    {
        $bindings = [':customer_id' => $customerId, ':discount_percent' => $terms['discount_percent'], ':payment_terms' => $terms['payment_terms']];
        $this->database->prepare(sql: $this->loadSqlFile('terms-upsert.sql'), bindings: $bindings)->execute();
    }
}
