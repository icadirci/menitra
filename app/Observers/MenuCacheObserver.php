<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Extra;
use App\Models\ExtraGroup;
use App\Models\Location;
use App\Models\LocationItemState;
use App\Models\LocationMenu;
use App\Models\LocationMenuHour;
use App\Models\LocationOpeningHour;
use App\Models\LocationSpecialHour;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAllergen;
use App\Models\MenuItemDietaryLabel;
use App\Models\MenuItemExtraGroup;
use App\Models\MenuItemVariant;
use App\Models\QrCode;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantLocale;
use App\Models\ThemeSetting;
use App\Models\Translations\ExtraGroupTranslation;
use App\Models\Translations\ExtraTranslation;
use App\Models\Translations\LocationTranslation;
use App\Models\Translations\MenuCategoryTranslation;
use App\Models\Translations\MenuItemTranslation;
use App\Models\Translations\MenuItemVariantTranslation;
use App\Models\Translations\MenuTranslation;
use App\Models\Translations\TenantTranslation;
use App\Services\Menu\MenuCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Maps every write that affects public menu output onto a cache version bump
 * (docs/architecture/caching.md § Invalidation matrix). MenuCache defers the
 * bump until the transaction commits.
 *
 * Location details appear in the tenant's location index, so they bump the
 * tenant version; purely operational branch state bumps only the location.
 * Bulk query-builder writes don't fire model events: services that use them
 * bump explicitly.
 */
final class MenuCacheObserver
{
    /** Content shared by every location of the tenant. */
    public const array TENANT_MODELS = [
        Menu::class, MenuCategory::class, MenuItem::class, MenuItemVariant::class, ExtraGroup::class,
        Extra::class, MenuItemExtraGroup::class, MenuItemAllergen::class, MenuItemDietaryLabel::class,
        Location::class, TenantLocale::class,
    ];

    /** Operational state of one branch (model has location_id). */
    public const array LOCATION_MODELS = [
        LocationMenu::class, LocationItemState::class, LocationOpeningHour::class, LocationSpecialHour::class,
    ];

    /** Translation model => [parent table, foreign key]. */
    public const array TRANSLATIONS = [
        MenuTranslation::class => ['menus', 'menu_id'],
        MenuCategoryTranslation::class => ['menu_categories', 'menu_category_id'],
        MenuItemTranslation::class => ['menu_items', 'menu_item_id'],
        MenuItemVariantTranslation::class => ['menu_item_variants', 'menu_item_variant_id'],
        ExtraGroupTranslation::class => ['extra_groups', 'extra_group_id'],
        ExtraTranslation::class => ['extras', 'extra_id'],
        LocationTranslation::class => ['locations', 'location_id'],
    ];

    /**
     * @return list<class-string<Model>>
     */
    public static function observed(): array
    {
        return [
            ...self::TENANT_MODELS,
            ...self::LOCATION_MODELS,
            ...array_keys(self::TRANSLATIONS),
            LocationMenuHour::class, Tenant::class, TenantTranslation::class, ThemeSetting::class, Subscription::class, QrCode::class,
        ];
    }

    public function __construct(private readonly MenuCache $cache) {}

    public function saved(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        $class = $model::class;

        match (true) {
            in_array($class, self::TENANT_MODELS, true) => $this->cache->bumpTenant((int) $model->getAttribute('tenant_id')),
            in_array($class, self::LOCATION_MODELS, true) => $this->cache->bumpLocation(
                (int) $model->getAttribute('tenant_id'),
                (int) $model->getAttribute('location_id'),
            ),
            isset(self::TRANSLATIONS[$class]) => $this->translation($model, ...self::TRANSLATIONS[$class]),
            $model instanceof LocationMenuHour => $this->menuHour($model),
            $model instanceof Tenant => $this->tenant($model),
            // Theme settings travel in the resolution entries (ResolvedTenant::$themeSettings).
            $model instanceof TenantTranslation, $model instanceof ThemeSetting => $this->tenantId((int) $model->getAttribute('tenant_id')),
            // Resolution entries hold access_until.
            $model instanceof Subscription => $this->cache->forgetTenant(
                (string) DB::table('tenants')->where('id', $model->getAttribute('tenant_id'))->value('slug'),
            ),
            $model instanceof QrCode => $this->cache->forgetQr((string) $model->code, (string) $model->getOriginal('code')),
            default => null,
        };
    }

    private function translation(Model $model, string $table, string $foreignKey): void
    {
        // A plain table read (no tenant scope involved): the parent row's owner.
        $tenantId = DB::table($table)->where('id', $model->getAttribute($foreignKey))->value('tenant_id');

        if ($tenantId !== null) {
            $this->cache->bumpTenant((int) $tenantId);
        }
    }

    private function menuHour(LocationMenuHour $hour): void
    {
        $locationId = DB::table('location_menu')->where('id', $hour->location_menu_id)->value('location_id');

        if ($locationId !== null) {
            $this->cache->bumpLocation((int) $hour->tenant_id, (int) $locationId);
        }
    }

    private function tenant(Tenant $tenant): void
    {
        $this->cache->bumpTenant((int) $tenant->getKey());
        $this->cache->forgetTenant((string) $tenant->slug, (string) $tenant->getOriginal('slug'));
    }

    private function tenantId(int $tenantId): void
    {
        $this->cache->bumpTenant($tenantId);
        $this->cache->forgetTenant((string) DB::table('tenants')->where('id', $tenantId)->value('slug'));
    }
}
