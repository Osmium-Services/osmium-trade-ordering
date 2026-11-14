<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/** How each shop category is treated on the trade price list (see TradeCategoryModes). */
class TradeCategoryRules extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'trade_category_rules');
    }

    /** @return array<int, string> category id => mode, for the categories that are not their own section */
    public function modes(): array
    {
        $rows = $this->database->prepare(sql: $this->loadSqlFile('category-rules-list.sql'), bindings: [])->resultset();

        $modes = [];
        foreach ($rows as $row) {
            $modes[(int) $row['category_id']] = (string) $row['mode'];
        }

        return $modes;
    }

    /** @param array<int, string> $modes The whole set, replacing the old one */
    public function replace(array $modes): void
    {
        $this->database->prepare(sql: $this->loadSqlFile('category-rules-clear.sql'), bindings: [])->execute();

        foreach ($modes as $categoryId => $mode) {
            $this->database->prepare(sql: $this->loadSqlFile('category-rules-insert.sql'), bindings: [':category_id' => $categoryId, ':mode' => $mode])->execute();
        }
    }
}
