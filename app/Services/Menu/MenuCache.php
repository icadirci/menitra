<?php

declare(strict_types=1);

namespace App\Services\Menu;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Version-counter cache for public menus. Writes bump a tenant or a location
 * version (atomic INCR); snapshot keys embed both versions, so stale entries
 * simply become unreachable and expire through their TTL.
 *
 * Bumps and resolution cache clears run after the surrounding transaction
 * commits (immediately when there is none), so a rollback never invalidates.
 */
final class MenuCache
{
    public function tenantVersion(int $tenantId): int
    {
        return (int) (Cache::get(MenuCacheKeys::tenantVersion($tenantId)) ?? 1);
    }

    /**
     * @return array{int, int} [tenant version, location version] in one round trip
     */
    public function versions(int $tenantId, int $locationId): array
    {
        $tenantKey = MenuCacheKeys::tenantVersion($tenantId);
        $locationKey = MenuCacheKeys::locationVersion($tenantId, $locationId);
        $values = Cache::many([$tenantKey, $locationKey]);

        return [(int) ($values[$tenantKey] ?? 1), (int) ($values[$locationKey] ?? 1)];
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember($key, (int) config('tenancy.cache.snapshot_ttl'), $callback);
    }

    public function bumpTenant(int $tenantId): void
    {
        $this->afterCommit(fn () => $this->increment(MenuCacheKeys::tenantVersion($tenantId)));
    }

    public function bumpLocation(int $tenantId, int $locationId): void
    {
        $this->afterCommit(fn () => $this->increment(MenuCacheKeys::locationVersion($tenantId, $locationId)));
    }

    /**
     * Clears the cached tenant resolution (slug and subdomain host entries).
     */
    public function forgetTenant(string ...$slugs): void
    {
        $this->afterCommit(function () use ($slugs): void {
            foreach (array_filter($slugs) as $slug) {
                Cache::forget(MenuCacheKeys::slug($slug));
                Cache::forget(MenuCacheKeys::host($slug.'.'.config('tenancy.central_domain')));
            }
        });
    }

    public function forgetQr(string ...$codes): void
    {
        $this->afterCommit(function () use ($codes): void {
            foreach (array_filter($codes) as $code) {
                Cache::forget(MenuCacheKeys::qr($code));
            }
        });
    }

    private function increment(string $key): void
    {
        // A missing counter reads as 1; create it first so the bump always changes it.
        Cache::add($key, 1);
        Cache::increment($key);
    }

    private function afterCommit(Closure $callback): void
    {
        DB::afterCommit($callback);
    }
}
