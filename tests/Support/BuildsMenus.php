<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\MenuType;
use App\Models\Location;
use App\Models\LocationMenu;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantContext;
use Closure;

/**
 * Builds tenant menu content directly through the models (inside the tenant's
 * context, which is cleared again afterwards).
 */
trait BuildsMenus
{
    /**
     * @param  array<string, int|null>  $limits
     */
    protected function subscribedTenant(string $slug, array $limits = []): Tenant
    {
        $tenant = Tenant::factory()->create(['slug' => $slug]);
        $plan = Plan::factory()->withLimits($limits + [
            'max_locations' => 10, 'max_menus' => 10, 'max_items' => 100, 'max_languages' => 3, 'max_qr_codes' => 10,
        ])->create();
        app(SubscriptionService::class)->startTrial($tenant, $plan);

        return $tenant;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function inTenant(Tenant $tenant, Closure $callback): mixed
    {
        $context = app(TenantContext::class);
        $previous = $context->check() ? $context->tenant() : null;
        $context->set($tenant);

        try {
            return $callback();
        } finally {
            $previous !== null ? $context->set($previous) : $context->clear();
        }
    }

    protected function location(Tenant $tenant, string $slug, bool $default = false): Location
    {
        return Location::factory()->for($tenant)->create(['slug' => $slug, 'is_default' => $default]);
    }

    /**
     * @param  list<Location>  $locations
     */
    protected function menu(Tenant $tenant, string $slug, array $locations, bool $published = true, string $name = 'Ana Menü'): Menu
    {
        return $this->inTenant($tenant, function () use ($slug, $locations, $published, $name): Menu {
            $menu = Menu::query()->create(['slug' => $slug, 'type' => MenuType::AllDay, 'is_published' => $published]);
            $menu->translations()->create(['locale' => 'tr', 'name' => $name]);

            foreach ($locations as $location) {
                LocationMenu::query()->create(['location_id' => $location->getKey(), 'menu_id' => $menu->getKey()]);
            }

            return $menu;
        });
    }

    protected function category(Menu $menu, string $name, bool $visible = true): MenuCategory
    {
        return $this->inTenant($menu->tenant, function () use ($menu, $name, $visible): MenuCategory {
            $category = $menu->categories()->create(['is_visible' => $visible]);
            $category->translations()->create(['locale' => 'tr', 'name' => $name]);

            return $category;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function item(MenuCategory $category, string $name, ?int $price = 10000, array $attributes = [], ?string $english = null): MenuItem
    {
        return $this->inTenant(Tenant::query()->findOrFail($category->tenant_id), function () use ($category, $name, $price, $attributes, $english): MenuItem {
            $item = $category->items()->create(['price' => $price, ...$attributes]);
            $item->translations()->create(['locale' => 'tr', 'name' => $name]);

            if ($english !== null) {
                $item->translations()->create(['locale' => 'en', 'name' => $english]);
            }

            return $item;
        });
    }
}
