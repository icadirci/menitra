<?php

declare(strict_types=1);

namespace App\Services\Menu;

use App\Exceptions\MenuUnavailableException;
use App\Models\Location;
use App\Models\Menu;
use App\Models\Tenant;
use App\Support\Menu\Data\LocationSummary;
use App\Support\Menu\Data\MenuSnapshot;
use App\Support\Menu\Data\TenantLocations;
use App\Tenancy\ResolvedTenant;
use App\Tenancy\TenantContext;
use Closure;

/**
 * Public menu data: access check, then cached snapshots keyed by tenant and
 * location versions (docs/architecture/caching.md). With a warm cache no
 * database query runs. On a miss the tenant's TenantContext is set only for
 * the duration of the build.
 */
final class PublicMenuService
{
    public function __construct(
        private readonly MenuCache $cache,
        private readonly MenuSnapshotBuilder $builder,
        private readonly TenantContext $context,
    ) {}

    /**
     * @throws MenuUnavailableException
     */
    public function ensureOnline(ResolvedTenant $tenant, string $locale): void
    {
        if (! $tenant->isOnline()) {
            throw new MenuUnavailableException($tenant->name($locale));
        }
    }

    public function locations(ResolvedTenant $tenant, string $locale): TenantLocations
    {
        $version = $this->cache->tenantVersion($tenant->id);

        return $this->cache->remember(
            MenuCacheKeys::tenantLocations($tenant->id, $locale, $version),
            fn (): TenantLocations => $this->withTenant($tenant, fn (Tenant $model): TenantLocations => new TenantLocations(
                brand: $this->builder->brand($model, $locale, ! $tenant->removeBranding),
                locale: $locale,
                availableLocales: $this->builder->locales($tenant->locales),
                locations: $this->builder->locationSummaries($model, $locale),
                version: (string) $version,
            )),
        );
    }

    /**
     * Location landing data; 404 for unknown or inactive branches.
     */
    public function location(ResolvedTenant $tenant, string $locale, string $locationSlug): MenuSnapshot
    {
        return $this->locationContext($tenant, $locale, $locationSlug)['snapshot'];
    }

    /**
     * A menu page; 404 when the menu isn't published or attached to the branch.
     */
    public function menu(ResolvedTenant $tenant, string $locale, string $locationSlug, string $menuSlug): MenuSnapshot
    {
        ['snapshot' => $base, 'location' => $location, 'tenantVersion' => $tv, 'locationVersion' => $lv] = $this->locationContext($tenant, $locale, $locationSlug);

        $summary = $base->findMenu($menuSlug);
        abort_if($summary === null, 404);

        return $this->cache->remember(
            MenuCacheKeys::menuSnapshot($tenant->id, $location->id, $summary->id, $locale, $tv, $lv),
            fn (): MenuSnapshot => $this->withTenant($tenant, function (Tenant $model) use ($base, $location, $summary, $locale): MenuSnapshot {
                $locationModel = Location::query()->findOrFail($location->id);
                $menu = Menu::query()->where('is_published', true)->findOrFail($summary->id);

                return $base->withMenu($this->builder->menu($model, $locationModel, $menu, $locale));
            }),
        );
    }

    /**
     * @return array{snapshot: MenuSnapshot, location: LocationSummary, tenantVersion: int, locationVersion: int}
     */
    private function locationContext(ResolvedTenant $tenant, string $locale, string $locationSlug): array
    {
        $location = null;

        foreach ($this->locations($tenant, $locale)->locations as $candidate) {
            if ($candidate->slug === $locationSlug) {
                $location = $candidate;
            }
        }

        abort_if($location === null, 404);

        [$tv, $lv] = $this->cache->versions($tenant->id, $location->id);

        $snapshot = $this->cache->remember(
            MenuCacheKeys::locationIndex($tenant->id, $location->id, $locale, $tv, $lv),
            fn (): MenuSnapshot => $this->withTenant($tenant, fn (Tenant $model): MenuSnapshot => $this->builder->location(
                $model,
                Location::query()->where('is_active', true)->findOrFail($location->id),
                $locale,
                $tenant->locales,
                "{$tv}.{$lv}",
                ! $tenant->removeBranding,
            )),
        );

        return ['snapshot' => $snapshot, 'location' => $location, 'tenantVersion' => $tv, 'locationVersion' => $lv];
    }

    /**
     * @template T
     *
     * @param  Closure(Tenant): T  $callback
     * @return T
     */
    private function withTenant(ResolvedTenant $tenant, Closure $callback): mixed
    {
        $model = Tenant::query()->findOrFail($tenant->id);
        $previous = $this->context->check() ? $this->context->tenant() : null;
        $this->context->set($model);

        try {
            return $callback($model);
        } finally {
            $previous !== null ? $this->context->set($previous) : $this->context->clear();
        }
    }
}
