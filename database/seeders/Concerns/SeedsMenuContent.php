<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use App\Enums\ExtraSelectionType;
use App\Enums\MenuTheme;
use App\Enums\MenuType;
use App\Enums\TenantStatus;
use App\Models\Extra;
use App\Models\ExtraGroup;
use App\Models\Location;
use App\Models\LocationMenu;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Tenant;

/**
 * Builders for seeded businesses and menu content (ShowcaseSeeder, DemoSeeder).
 * Everything except tenant() runs inside the TenantContext set by the caller.
 */
trait SeedsMenuContent
{
    /**
     * @param  array<string, array<string, string>>  $translations
     */
    private function tenant(string $slug, array $translations, TenantStatus $status = TenantStatus::Active): Tenant
    {
        $tenant = new Tenant(['slug' => $slug, 'currency' => 'TRY', 'default_locale' => 'tr', 'theme' => MenuTheme::Classic]);
        $tenant->forceFill(['status' => $status])->save();

        foreach ($translations as $locale => $values) {
            $tenant->translations()->create(['locale' => $locale, ...$values]);
        }

        return $tenant;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>  $names
     * @param  array<string, string>  $descriptions
     */
    private function location(string $slug, bool $default, array $attributes, array $names, array $descriptions = []): Location
    {
        $location = new Location(['slug' => $slug, 'is_active' => true, 'timezone' => 'Europe/Istanbul', 'country_code' => 'TR', ...$attributes]);
        $location->forceFill(['is_default' => $default, 'sort_order' => $default ? 0 : 1])->save();

        foreach ($names as $locale => $name) {
            $location->translations()->create(['locale' => $locale, 'name' => $name, 'description' => $descriptions[$locale] ?? null]);
        }

        return $location;
    }

    /**
     * @param  array<string, string>  $names
     * @param  list<Location>  $locations
     * @param  array{string, string}|null  $servingHours
     */
    private function menu(string $slug, MenuType $type, array $names, array $locations, ?array $servingHours = null): Menu
    {
        $menu = Menu::query()->create(['slug' => $slug, 'type' => $type, 'is_published' => true, 'sort_order' => $type === MenuType::Breakfast ? 0 : 1]);

        foreach ($names as $locale => $name) {
            $menu->translations()->create(['locale' => $locale, 'name' => $name]);
        }

        foreach ($locations as $location) {
            $attachment = LocationMenu::query()->create([
                'location_id' => $location->getKey(), 'menu_id' => $menu->getKey(), 'is_active' => true,
                'sort_order' => $type === MenuType::Breakfast ? 0 : 1,
            ]);

            if ($servingHours !== null) {
                foreach (range(1, 7) as $day) {
                    $attachment->hours()->create(['day_of_week' => $day, 'starts_at' => $servingHours[0], 'ends_at' => $servingHours[1]]);
                }
            }
        }

        return $menu;
    }

    /**
     * @param  array<string, string>  $names
     */
    private function category(Menu $menu, array $names): MenuCategory
    {
        $category = $menu->categories()->create(['is_visible' => true, 'sort_order' => (int) $menu->categories()->max('sort_order') + 1]);

        foreach ($names as $locale => $name) {
            $category->translations()->create(['locale' => $locale, 'name' => $name]);
        }

        return $category;
    }

    /**
     * @param  array<string, array{string, string}>  $texts  locale => [name, description]
     * @param  list<string>  $diet
     * @param  list<string>  $allergens
     * @param  list<ExtraGroup>  $extras
     * @param  list<array{array<string, string>, int, bool}>  $variants
     */
    private function item(
        MenuCategory $category,
        ?int $price,
        array $texts,
        bool $featured = false,
        bool $popular = false,
        array $diet = [],
        array $allergens = [],
        array $extras = [],
        array $variants = [],
        ?int $prep = null,
        ?int $calories = null,
    ): MenuItem {
        $item = $category->items()->create([
            'price' => $price, 'is_visible' => true, 'is_available' => true, 'is_featured' => $featured, 'is_popular' => $popular,
            'preparation_minutes' => $prep, 'calories' => $calories, 'sort_order' => (int) $category->items()->max('sort_order') + 1,
        ]);

        foreach ($texts as $locale => [$name, $description]) {
            $item->translations()->create(['locale' => $locale, 'name' => $name, 'description' => $description]);
        }

        foreach ($diet as $label) {
            $item->dietaryLabels()->create(['label' => $label]);
        }

        foreach ($allergens as $allergen) {
            $item->allergens()->create(['allergen' => $allergen]);
        }

        foreach ($extras as $position => $group) {
            $item->extraGroups()->attach($group->getKey(), ['sort_order' => $position]);
        }

        foreach ($variants as $position => [$names, $variantPrice, $isDefault]) {
            $variant = $item->variants()->create(['price' => $variantPrice, 'is_default' => $isDefault, 'is_available' => true, 'sort_order' => $position]);

            foreach ($names as $locale => $name) {
                $variant->translations()->create(['locale' => $locale, 'name' => $name]);
            }
        }

        return $item;
    }

    /**
     * @param  array<string, string>  $names
     * @param  list<array{array<string, string>, int}>  $options
     */
    private function extraGroup(ExtraSelectionType $type, array $names, array $options, ?int $max = null): ExtraGroup
    {
        $group = ExtraGroup::query()->create([
            'selection_type' => $type, 'min_select' => 0, 'max_select' => $type === ExtraSelectionType::Single ? 1 : $max,
            'is_required' => false, 'sort_order' => (int) ExtraGroup::query()->max('sort_order') + 1,
        ]);

        foreach ($names as $locale => $name) {
            $group->translations()->create(['locale' => $locale, 'name' => $name]);
        }

        foreach ($options as $position => [$optionNames, $delta]) {
            /** @var Extra $extra */
            $extra = $group->extras()->create(['price_delta' => $delta, 'is_available' => true, 'sort_order' => $position]);

            foreach ($optionNames as $locale => $name) {
                $extra->translations()->create(['locale' => $locale, 'name' => $name]);
            }
        }

        return $group;
    }
}
