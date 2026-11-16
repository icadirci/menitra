<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Enums\PlanFeature;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Menu\MenuCacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds and caches ResolvedTenant values. Used only by the tenant resolvers
 * and QrCodeResolver (documented scope bypass: the tenant isn't known yet).
 */
final class ResolvedTenantFactory
{
    public function bySlug(string $slug, string $cacheKey): ?ResolvedTenant
    {
        if (preg_match('/^[a-z0-9](-?[a-z0-9])*$/', $slug) !== 1 || strlen($slug) > 40) {
            return null;
        }

        return Cache::remember($cacheKey, (int) config('tenancy.cache.resolution_ttl'), function () use ($slug): ?ResolvedTenant {
            $tenant = Tenant::query()->where('slug', $slug)->first();

            return $tenant === null ? null : $this->make($tenant);
        });
    }

    /**
     * The current slug of the (non-deleted) tenant that used to have this slug
     * (tenant_slug_redirects). Asked only after resolution failed, so not
     * cached; public menu routes are throttled.
     */
    public function currentSlugFor(string $formerSlug): ?string
    {
        if (preg_match('/^[a-z0-9](-?[a-z0-9])*$/', $formerSlug) !== 1 || strlen($formerSlug) > 40) {
            return null;
        }

        $slug = DB::table('tenant_slug_redirects')
            ->join('tenants', 'tenants.id', '=', 'tenant_slug_redirects.tenant_id')
            ->where('tenant_slug_redirects.slug', $formerSlug)
            ->whereNull('tenants.deleted_at')
            ->value('tenants.slug');

        return $slug === null ? null : (string) $slug;
    }

    public function byId(int $id): ?ResolvedTenant
    {
        $slug = Tenant::query()->whereKey($id)->value('slug');

        return $slug === null ? null : $this->bySlug((string) $slug, MenuCacheKeys::slug((string) $slug));
    }

    public function make(Tenant $tenant): ResolvedTenant
    {
        $locales = DB::table('tenant_locales')
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('locale')
            ->all();

        $names = DB::table('tenant_translations')
            ->where('tenant_id', $tenant->getKey())
            ->pluck('name', 'locale')
            ->map(fn ($name): string => (string) $name)
            ->all();

        $themeSettings = DB::table('theme_settings')
            ->where('tenant_id', $tenant->getKey())
            ->pluck('settings', 'theme')
            ->map(fn ($settings): array => (array) json_decode((string) $settings, true))
            ->all();

        $subscription = Subscription::withoutTenantScope()
            ->where('tenant_id', $tenant->getKey())
            ->whereNull('ended_at')
            ->with('plan.limits')
            ->latest('id')
            ->first();

        $limits = in_array($subscription?->status, [
            SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::Grace,
        ], true)
            ? $subscription->plan?->limits->mapWithKeys(fn ($limit): array => [$limit->feature->value => $limit->limit_value])->all() ?? []
            : [];

        $enabled = fn (PlanFeature $feature): bool => array_key_exists($feature->value, $limits)
            && ($limits[$feature->value] === null || $limits[$feature->value] >= 1);

        return new ResolvedTenant(
            id: (int) $tenant->getKey(),
            slug: (string) $tenant->slug,
            status: $tenant->status->value,
            defaultLocale: (string) $tenant->default_locale,
            locales: array_values(array_unique([$tenant->default_locale, ...$locales])),
            theme: $tenant->theme->value,
            accessUntil: $subscription?->accessUntil()?->getTimestamp(),
            names: $names,
            premiumThemes: $enabled(PlanFeature::PremiumThemes),
            removeBranding: $enabled(PlanFeature::RemoveBranding),
            themeSettings: $themeSettings,
        );
    }
}
