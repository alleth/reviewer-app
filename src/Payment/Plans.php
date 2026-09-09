<?php
declare(strict_types=1);

namespace App\Payment;

/**
 * Server-side source of truth for the access-pass prices and durations. The
 * frontend has its own display copy in webroot/react-frontend/src/plans.js —
 * keep the id / name / days / price in sync between the two (same convention as
 * the pre-paint theme script ↔ src/theme.js).
 *
 * Checkout amounts are always taken from here, never from the client.
 */
final class Plans
{
    /**
     * @var array<string, array{name: string, days: int, price: float}>
     */
    public const PLANS = [
        '7-day' => ['name' => '7-Day Access', 'days' => 7, 'price' => 49.0],
        '30-day' => ['name' => '30-Day Access', 'days' => 30, 'price' => 174.07],
        '90-day' => ['name' => '90-Day Access', 'days' => 90, 'price' => 399.0],
    ];

    /**
     * @param string $id Plan id.
     * @return array{name: string, days: int, price: float}|null
     */
    public static function get(string $id): ?array
    {
        return self::PLANS[$id] ?? null;
    }
}
