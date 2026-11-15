<?php

declare(strict_types=1);

namespace App\Support\Menu;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The single "is the public menu online" rule (subscriptions.md § Public
 * menu access):
 *
 *   online ⇔ tenant active AND access_until ≠ null AND now ≤ access_until
 *
 * Used by the public menu (ResolvedTenant), the business dashboard and
 * /account (BusinessDashboard), so they can never disagree.
 */
final class MenuAvailability
{
    public static function isOnline(
        bool $tenantActive,
        CarbonInterface|int|null $accessUntil,
        CarbonInterface|int|null $now = null,
    ): bool {
        if (! $tenantActive || $accessUntil === null) {
            return false;
        }

        $until = $accessUntil instanceof CarbonInterface ? $accessUntil->getTimestamp() : $accessUntil;
        $at = match (true) {
            $now instanceof CarbonInterface => $now->getTimestamp(),
            is_int($now) => $now,
            default => CarbonImmutable::now()->getTimestamp(),
        };

        return $at <= $until;
    }
}
