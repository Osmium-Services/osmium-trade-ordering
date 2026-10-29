<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/** The states of a trade order, as SolarGo2 has them. Pure. */
final class TradeOrderStatus
{
    public const DRAFT = 'draft';
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const PROCESSING = 'processing';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';

    public const ALL = [self::DRAFT, self::PENDING, self::CONFIRMED, self::PROCESSING, self::SHIPPED, self::DELIVERED, self::CANCELLED];

    /** What an admin can change a submitted order to (a draft is the customer's own until submitted). */
    public const ADMIN_CHOICES = [self::PENDING, self::CONFIRMED, self::PROCESSING, self::SHIPPED, self::DELIVERED, self::CANCELLED];

    public static function isValid(string $status): bool
    {
        return \in_array($status, self::ALL, true);
    }

    public static function label(string $status): string
    {
        return \ucfirst($status);
    }

    /** Only a draft with something in it can be submitted. */
    public static function canSubmit(string $status, int $itemCount): bool
    {
        return $status === self::DRAFT && $itemCount > 0;
    }
}
