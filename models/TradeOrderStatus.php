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

    /** Orders still being worked on, and orders that are finished with, as the admin lists them */
    public const OPEN = [self::PENDING, self::CONFIRMED, self::PROCESSING, self::SHIPPED];
    public const CLOSED = [self::DELIVERED, self::CANCELLED];

    /** The step after each status, as the admin's one-click action */
    private const NEXT = [self::PENDING => self::CONFIRMED, self::CONFIRMED => self::PROCESSING, self::PROCESSING => self::SHIPPED, self::SHIPPED => self::DELIVERED];

    /** The next step in the workflow, or null when there is none */
    public static function next(string $status): ?string
    {
        return self::NEXT[$status] ?? null;
    }

    /** A badge colour for the admin list */
    public static function badgeClass(string $status): string
    {
        return match ($status) {
            self::PENDING => 'warning',
            self::CONFIRMED => 'info',
            self::PROCESSING => 'primary',
            self::SHIPPED, self::DELIVERED => 'success',
            self::CANCELLED => 'danger',
            default => 'secondary',
        };
    }

    /** An admin can move a submitted order to any admin status other than the one it has; a draft is the customer's own */
    public static function adminCanSet(string $current, string $new): bool
    {
        $known = \in_array($new, self::ADMIN_CHOICES, true);
        $submitted = $current !== self::DRAFT && self::isValid($current);

        return $known && $submitted && $current !== $new;
    }

    /** Only a draft with something in it can be submitted. */
    public static function canSubmit(string $status, int $itemCount): bool
    {
        return $status === self::DRAFT && $itemCount > 0;
    }
}
