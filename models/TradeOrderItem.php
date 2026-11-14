<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/** The lines of a trade order. */
class TradeOrderItem extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'trade_order_items');
    }

    /** @return array<int, array<string, mixed>> */
    public function listByOrder(int $orderId): array
    {
        return $this->database->prepare(sql: $this->loadSqlFile('item-list-by-order.sql'), bindings: [':order_id' => $orderId])->resultset();
    }

    /** @return array<int, array<string, mixed>> every line of one product on an order (the same product can be there with different options) */
    public function listByProduct(int $orderId, int $productId): array
    {
        return $this->database->prepare(sql: $this->loadSqlFile('item-list-by-product.sql'), bindings: [':order_id' => $orderId, ':product_id' => $productId])->resultset();
    }

    /** @param array{product_ref: string, product_title: string, options_json?: string, options_display?: string, quantity: int, unit_exc_tax: float, unit_inc_tax: float, line_exc_tax: float, line_inc_tax: float} $line */
    public function insert(int $orderId, int $productId, array $line): int
    {
        $bindings = [
            ':order_id' => $orderId,
            ':product_id' => $productId,
            ':product_ref' => $line['product_ref'],
            ':product_title' => $line['product_title'],
            ':options_json' => ($line['options_json'] ?? '') === '' ? null : $line['options_json'],
            ':options_display' => ($line['options_display'] ?? '') === '' ? null : $line['options_display'],
            ':quantity' => $line['quantity'],
            ':unit_exc' => $line['unit_exc_tax'],
            ':unit_inc' => $line['unit_inc_tax'],
            ':line_exc' => $line['line_exc_tax'],
            ':line_inc' => $line['line_inc_tax'],
        ];
        $this->database->prepare(sql: $this->loadSqlFile('item-insert.sql'), bindings: $bindings)->execute();

        return (int) $this->database->lastInsertId();
    }

    public function setQuantity(int $orderId, int $itemId, int $quantity): void
    {
        $bindings = [':order_id' => $orderId, ':item_id' => $itemId, ':quantity' => $quantity];
        $this->database->prepare(sql: $this->loadSqlFile('item-update-quantity.sql'), bindings: $bindings)->execute();
    }

    public function delete(int $orderId, int $itemId): void
    {
        $this->database->prepare(sql: $this->loadSqlFile('item-delete.sql'), bindings: [':order_id' => $orderId, ':item_id' => $itemId])->execute();
    }
}
