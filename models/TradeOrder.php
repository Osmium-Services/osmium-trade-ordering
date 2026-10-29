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

    /**
     * @param string[] $statuses
     * @return array<int, array<string, mixed>> orders in those statuses with the customer's name and email, newest first
     */
    public function listForAdmin(array $statuses): array
    {
        $valid = \array_values(\array_filter($statuses, TradeOrderStatus::isValid(...)));
        $none = $valid === [];
        if ($none) return [];

        return $this->database->prepare(sql: $this->loadSqlFile('order-list-admin.sql'), bindings: [':statuses' => \implode(',', $valid)])->resultset();
    }

    /** @return array<string, mixed>|null */
    public function getForAdmin(int $id): ?array
    {
        return $this->one(file: 'order-get-admin.sql', bindings: [':id' => $id]);
    }

    /** @return array{total: float, orders: int} what has been ordered this calendar month, not counting drafts or cancelled orders */
    public function monthValue(): array
    {
        $row = $this->one(file: 'order-month-value.sql', bindings: []);

        return ['total' => (float) ($row['total'] ?? 0), 'orders' => (int) ($row['orders'] ?? 0)];
    }

    /** A draft is never changed this way */
    public function setStatus(int $id, string $status): void
    {
        $this->database->prepare(sql: $this->loadSqlFile('order-set-status.sql'), bindings: [':id' => $id, ':status' => $status])->execute();
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
