<?php

declare(strict_types=1);

/**
 * Trade order lists, rendered by Osmium\Modules\Admin\Core\ListView. Row fields (statusLabel, totalText...) are prepared by
 * TradeOrdersController::formatOrder().
 */
$orderColumns = [
    ['key' => 'reference', 'label' => 'Order', 'type' => 'text', 'strong' => true, 'nowrap' => true, 'sub' => ['key' => 'submittedText', 'style' => 'muted']],
    ['key' => 'company_name', 'label' => 'Customer', 'type' => 'text', 'fill' => true, 'fallback' => '-', 'sub' => ['key' => 'email', 'style' => 'muted']],
    ['key' => 'item_count', 'label' => 'Items', 'type' => 'text', 'small' => true, 'nowrap' => true],
    ['key' => 'totalText', 'label' => 'Total inc tax', 'type' => 'text', 'small' => true, 'nowrap' => true],
    ['key' => 'statusLabel', 'label' => 'Status', 'type' => 'badge', 'text' => true, 'classKey' => 'statusClass', 'nowrap' => true],
];

$orderActions = [
    ['label' => 'View details', 'icon' => 'bx-show', 'event' => 'view'],
    [
        'label' => 'Confirm',
        'icon' => 'bx-check',
        'action' => 'status_confirmed',
        'visible' => static fn(array $row, array $context): bool => $row['status'] === 'pending',
    ],
    [
        'label' => 'Mark processing',
        'icon' => 'bx-cog',
        'action' => 'status_processing',
        'visible' => static fn(array $row, array $context): bool => $row['status'] === 'confirmed',
    ],
    [
        'label' => 'Mark shipped',
        'icon' => 'bx-package',
        'action' => 'status_shipped',
        'visible' => static fn(array $row, array $context): bool => $row['status'] === 'processing',
    ],
    [
        'label' => 'Mark delivered',
        'icon' => 'bx-check-double',
        'action' => 'status_delivered',
        'visible' => static fn(array $row, array $context): bool => $row['status'] === 'shipped',
    ],
    [
        'label' => 'Cancel order',
        'icon' => 'bx-x-circle',
        'action' => 'status_cancelled',
        'confirm' => 'Cancel this order?',
        'danger' => true,
        'visible' => static fn(array $row, array $context): bool => \in_array($row['status'], ['pending', 'confirmed', 'processing', 'shipped'], true),
    ],
    [
        'label' => 'Reopen as pending',
        'icon' => 'bx-undo',
        'action' => 'status_pending',
        'visible' => static fn(array $row, array $context): bool => $row['status'] === 'cancelled',
    ],
];

return [
    'open' => [
        'id' => 'trade-orders-open',
        'title' => 'Trade Orders',
        'noun' => ['order', 'orders'],
        'endpoint' => 'trade/orders/action/',
        'note' => 'Orders a trade customer has submitted that are not yet delivered or cancelled.',
        'emptyText' => 'No open trade orders.',
        'order' => [0, 'desc'],
        'columns' => $orderColumns,
        'actions' => $orderActions,
    ],

    'closed' => [
        'id' => 'trade-orders-closed',
        'title' => 'Delivered and Cancelled',
        'noun' => ['order', 'orders'],
        'icon' => 'bx-check-double',
        'endpoint' => 'trade/orders/action/',
        'collapsible' => ['label' => 'Delivered and Cancelled ({count})'],
        'hideWhenEmpty' => true,
        'order' => [0, 'desc'],
        'columns' => $orderColumns,
        'actions' => $orderActions,
    ],
];
