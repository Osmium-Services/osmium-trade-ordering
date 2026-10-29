<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/** Brands left out of the trade discount. */
class TradeBrandExclusions extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'trade_brand_exclusions');
    }

    /** @return int[] */
    public function ids(): array
    {
        $rows = $this->database->prepare(sql: $this->loadSqlFile('exclusions-list.sql'), bindings: [])->resultset();

        return \array_map(static fn(array $row): int => (int) $row['brand_id'], $rows);
    }

    /** @param int[] $brandIds The whole list, replacing the old one */
    public function replace(array $brandIds): void
    {
        $this->database->prepare(sql: $this->loadSqlFile('exclusions-clear.sql'), bindings: [])->execute();

        foreach ($brandIds as $brandId) {
            $this->database->prepare(sql: $this->loadSqlFile('exclusions-insert.sql'), bindings: [':brand_id' => $brandId])->execute();
        }
    }
}
