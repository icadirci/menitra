<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Menu\MenuAvailability;
use App\Tenancy\ResolvedTenant;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class MenuAvailabilityTest extends TestCase
{
    public function test_online_until_and_including_access_until(): void
    {
        $until = CarbonImmutable::parse('2026-11-22 12:00:00');

        $this->assertTrue(MenuAvailability::isOnline(true, $until, $until->subSecond()));
        $this->assertTrue(MenuAvailability::isOnline(true, $until, $until));
        $this->assertFalse(MenuAvailability::isOnline(true, $until, $until->addSecond()));
    }

    public function test_offline_without_access_or_for_an_inactive_tenant(): void
    {
        $now = CarbonImmutable::parse('2026-11-15 12:00:00');

        $this->assertFalse(MenuAvailability::isOnline(true, null, $now));
        $this->assertFalse(MenuAvailability::isOnline(false, $now->addDay(), $now));
    }

    public function test_timestamps_and_dates_give_the_same_answer(): void
    {
        $until = CarbonImmutable::parse('2026-11-22 12:00:00');

        $this->assertSame(
            MenuAvailability::isOnline(true, $until, $until),
            MenuAvailability::isOnline(true, $until->getTimestamp(), $until->getTimestamp()),
        );
    }

    public function test_the_public_menu_uses_the_same_rule(): void
    {
        $until = CarbonImmutable::parse('2026-11-22 12:00:00')->getTimestamp();
        $tenant = fn (string $status, ?int $accessUntil): ResolvedTenant => new ResolvedTenant(
            id: 1, slug: 'konak', status: $status, defaultLocale: 'tr', locales: ['tr'], theme: 'classic',
            accessUntil: $accessUntil, names: ['tr' => 'Konak'], premiumThemes: false, removeBranding: false,
        );

        foreach ([['active', $until], ['active', null], ['suspended', $until]] as [$status, $accessUntil]) {
            foreach ([$until - 1, $until, $until + 1] as $now) {
                $this->assertSame(
                    MenuAvailability::isOnline($status === 'active', $accessUntil, $now),
                    $tenant($status, $accessUntil)->isOnline($now),
                );
            }
        }
    }
}
