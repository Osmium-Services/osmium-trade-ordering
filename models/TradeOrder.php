<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/** Trade orders: one draft per customer (their basket), then submitted orders. */
class TradeOrder extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'trade_orders');
    }

    /** @return array<string, mixed>|null */
    public function getDraft(int $customerId): ?array
    {
        return $this->one(file: 'order-get-draft.sql', bindings: [':customer_id' => $customerId]);
    }

    /** @return array<string, mixed>|null */
    public function getById(int $id): ?array
    {
        return $this->one(file: 'order-get.sql', bindings: [':id' => $id]);
    }

    public function createDraft(int $customerId, float $discountPercent): int
    {
        $reference = 'DRAFT-' . $customerId . '-' . \time();
        $bindings = [':customer_id' => $customerId, ':reference' => $reference, ':discount_percent' => $discountPercent];
        $this->database->prepare(sql: $this->loadSqlFile('order-create-draft.sql'), bindings: $bindings)->execute();

        return (int) $this->database->lastInsertId();
    }

    public function updateTotals(int $orderId): void
    {
        $this->database->prepare(sql: $this->loadSqlFile('order-update-totals.sql'), bindings: [':order_id' => $orderId])->execute();
    }

    /** @return bool true when a draft was submitted */
    public function submit(int $orderId, string $reference, ?string $poReference, ?string $paymentTerms): bool
    {
        $bindings = [':order_id' => $orderId, ':reference' => $reference, ':po_reference' => $poReference, ':payment_terms' => $paymentTerms];
        $statement = $this->database->prepare(sql: $this->loadSqlFile('order-submit.sql'), bindings: $bindings);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    /** The highest submitted reference of a year ("TO-2026-%"), or null when there is none yet */
    public function highestReference(string $year): ?string
    {
        $row = $this->one(file: 'order-highest-reference.sql', bindings: [':year_prefix' => 'TO-' . $year . '-%']);

        return $row['highest'] ?? null;
    }

    /** @param array<string, mixed> $bindings @return array<string, mixed>|null */
    private function one(string $file, array $bindings): ?array
    {
        $row = $this->database->prepare(sql: $this->loadSqlFile($file), bindings: $bindings)->single();

        return $row ?: null;
    }
}
