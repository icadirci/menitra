<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enums\PlanFeature;
use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\TenantLocaleException;
use App\Models\Tenant;
use App\Models\TenantLocale;
use App\Services\Subscription\FeatureGate;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Manages the languages of the tenant in TenantContext.
 *
 * Invariants:
 * - only platform locales (config/locales.php) can be enabled;
 * - tenants.default_locale is always an enabled locale;
 * - disabling a locale never deletes its translations;
 * - enabling a locale is limited by PlanFeature::MaxLanguages.
 *
 * Authorization (owner only) is checked by the caller through TenantPolicy.
 */
final class TenantLocaleService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly FeatureGate $features,
    ) {}

    /**
     * @throws TenantLocaleException|PlanLimitExceededException
     */
    public function enable(string $locale): TenantLocale
    {
        $this->assertSupported($locale);

        return DB::transaction(function () use ($locale): TenantLocale {
            $tenant = $this->lockedTenant();

            if ($tenant->locales()->where('locale', $locale)->exists()) {
                throw TenantLocaleException::alreadyEnabled($locale);
            }

            $this->features->ensureCanCreate($tenant, PlanFeature::MaxLanguages);

            return $tenant->locales()->create([
                'locale' => $locale,
                'sort_order' => ((int) $tenant->locales()->max('sort_order')) + 1,
            ]);
        });
    }

    /**
     * Hides a language. Its translation rows stay stored and come back when
     * the language is enabled again.
     *
     * @throws TenantLocaleException
     */
    public function disable(string $locale): void
    {
        DB::transaction(function () use ($locale): void {
            $tenant = $this->lockedTenant();

            if ($tenant->default_locale === $locale) {
                throw TenantLocaleException::cannotDisableDefault();
            }

            if ($tenant->locales()->where('locale', $locale)->delete() === 0) {
                throw TenantLocaleException::notEnabled($locale);
            }
        });
    }

    /**
     * @throws TenantLocaleException
     */
    public function setDefault(string $locale): void
    {
        $this->assertSupported($locale);

        DB::transaction(function () use ($locale): void {
            $tenant = $this->lockedTenant();

            if (! $tenant->locales()->where('locale', $locale)->exists()) {
                throw TenantLocaleException::notEnabled($locale);
            }

            $tenant->forceFill(['default_locale' => $locale])->save();
        });
    }

    /**
     * @param  list<string>  $locales  every enabled locale exactly once, in the new order
     *
     * @throws TenantLocaleException
     */
    public function reorder(array $locales): void
    {
        DB::transaction(function () use ($locales): void {
            $tenant = $this->lockedTenant();
            $enabled = $tenant->locales()->pluck('locale')->all();

            if (count($locales) !== count(array_unique($locales))
                || array_diff($locales, $enabled) !== []
                || array_diff($enabled, $locales) !== []) {
                throw TenantLocaleException::invalidOrder();
            }

            foreach (array_values($locales) as $position => $locale) {
                $tenant->locales()->where('locale', $locale)->update(['sort_order' => $position]);
            }
        });
    }

    /**
     * The context's tenant instance, refreshed under a row lock so concurrent
     * language changes can't break the invariants. Updating the same instance
     * keeps TenantContext current for the rest of the request.
     */
    private function lockedTenant(): Tenant
    {
        $tenant = $this->context->tenant();
        $locked = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
        $tenant->setRawAttributes($locked->getAttributes(), sync: true);

        return $tenant;
    }

    private function assertSupported(string $locale): void
    {
        if (! array_key_exists($locale, (array) config('locales.supported'))) {
            throw TenantLocaleException::unsupported($locale);
        }
    }
}
