<?php

declare(strict_types=1);

namespace App\Services\Themes;

use App\Enums\MenuTheme;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\ThemeSetting;
use App\Services\Subscription\FeatureGate;
use App\Support\Menu\ThemeSettings;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Theme selection and per-theme settings of the current tenant
 * (docs/architecture/themes.md). Authorization is checked by the caller
 * (TenantPolicy::manageSettings, LocationPolicy::setTheme); this service
 * enforces the invariants:
 *
 * - only built themes (views exist) can be selected;
 * - choosing a theme that needs a plan feature (premium_themes) is gated by
 *   FeatureGate — keeping or editing the theme already in use is not, and
 *   saved settings are never deleted when the plan changes;
 * - settings are validated by MenuTheme::validator() (rules + contrast) and
 *   stored one row per tenant and theme, so switching back restores them.
 *
 * Cache invalidation follows from the model writes (MenuCacheObserver).
 * Validation error keys: `theme`, `settings.*`.
 */
final class ThemeService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly FeatureGate $gate,
    ) {}

    /**
     * Selects $theme for the tenant and saves its settings.
     *
     * @param  array<string, mixed>  $settings
     *
     * @throws ValidationException
     * @throws PlanLimitExceededException
     */
    public function save(Tenant $tenant, MenuTheme $theme, array $settings): ThemeSetting
    {
        $this->assertCurrentTenant($tenant);
        $this->ensureSelectable($tenant, $theme, $tenant->theme);

        $validator = $theme->validator($settings);

        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->toArray())
                ->mapWithKeys(fn (array $messages, string $key): array => ["settings.{$key}" => $messages])
                ->all());
        }

        return DB::transaction(function () use ($tenant, $theme, $settings): ThemeSetting {
            $row = $this->store($theme, $theme->normalize($settings));
            $tenant->theme = $theme;
            $tenant->save();

            return $row;
        });
    }

    /**
     * Theme override of a branch (null = use the tenant's theme). It uses
     * the tenant's settings of that theme.
     *
     * @throws ValidationException
     * @throws PlanLimitExceededException
     */
    public function setLocationTheme(Location $location, ?MenuTheme $theme): void
    {
        if ((int) $location->tenant_id !== $this->context->id()) {
            throw new CrossTenantWriteException('The location belongs to another tenant.');
        }

        if ($theme !== null) {
            $this->ensureSelectable($this->context->tenant(), $theme, $location->theme);
        }

        $location->forceFill(['theme' => $theme])->save();
    }

    /**
     * Default settings row written by registration (inside its transaction
     * and tenant context).
     */
    public function createDefaults(MenuTheme $theme): ThemeSetting
    {
        return $this->store($theme, $theme->defaults());
    }

    /**
     * Saved settings of $theme merged over its defaults (form state).
     *
     * @return array{colors: array<string, string>, font_pair: string, layout: array<string, bool|string>}
     */
    public function settingsFor(MenuTheme $theme): array
    {
        $stored = ThemeSetting::query()->where('theme', $theme)->value('settings');

        return ThemeSettings::merge($theme, is_array($stored) ? $stored : null);
    }

    /**
     * Whether the plan lets the tenant newly choose $theme (UI hint; save()
     * and setLocationTheme() enforce it).
     */
    public function planAllows(Tenant $tenant, MenuTheme $theme): bool
    {
        $feature = $theme->requiredFeature();

        return $feature === null || $this->gate->allows($tenant, $feature);
    }

    /**
     * @throws ValidationException
     * @throws PlanLimitExceededException
     */
    private function ensureSelectable(Tenant $tenant, MenuTheme $theme, ?MenuTheme $current): void
    {
        if (! $theme->isBuilt()) {
            throw ValidationException::withMessages(['theme' => __('panels.app.theme.errors.not_built')]);
        }

        // Keeping the theme in use is never blocked by the plan.
        if ($theme !== $current && ($feature = $theme->requiredFeature()) !== null) {
            $this->gate->ensureCanCreate($tenant, $feature);
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function store(MenuTheme $theme, array $settings): ThemeSetting
    {
        $row = ThemeSetting::query()->firstOrNew(['theme' => $theme]);
        $row->settings = $settings;
        $row->save();

        return $row;
    }

    private function assertCurrentTenant(Tenant $tenant): void
    {
        if (! $this->context->check() || $this->context->id() !== (int) $tenant->getKey()) {
            throw new CrossTenantWriteException('ThemeService can only change the tenant in the current TenantContext.');
        }
    }
}
