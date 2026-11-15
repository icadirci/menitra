<?php

declare(strict_types=1);

namespace App\Services\Menus;

use App\Enums\Allergen;
use App\Enums\DietaryLabel;
use App\Enums\PlanFeature;
use App\Exceptions\PlanLimitExceededException;
use App\Models\ExtraGroup;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Services\Subscription\FeatureGate;
use App\Services\Translations\TranslationWriter;
use App\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Categories, items and variants. The caller authorizes against the menu
 * (MenuPolicy::update); for items it must authorize the target category's
 * menu too. Everything here is tenant-scoped through BelongsToTenant and the
 * composite foreign keys.
 */
final class MenuContentService
{
    private const array CATEGORY_ATTRIBUTES = ['is_visible'];

    private const array ITEM_ATTRIBUTES = [
        'price', 'is_visible', 'is_available', 'is_featured', 'is_popular', 'calories', 'preparation_minutes',
    ];

    private const array VARIANT_ATTRIBUTES = ['price', 'is_default', 'is_available'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly FeatureGate $gate,
        private readonly TranslationWriter $translations,
    ) {}

    /**
     * @param  array<string, mixed>  $data  is_visible, image_path, translations
     *
     * @throws ValidationException
     */
    public function saveCategory(Menu $menu, array $data, ?MenuCategory $category = null): MenuCategory
    {
        return DB::transaction(function () use ($menu, $data, $category): MenuCategory {
            $category ??= $menu->categories()->make([
                'sort_order' => (int) $menu->categories()->max('sort_order') + 1,
            ]);
            $category->fill(Arr::only($data, self::CATEGORY_ATTRIBUTES));

            if (array_key_exists('image_path', $data)) {
                $category->forceFill(['image_path' => $data['image_path']]);
            }

            $category->save();
            $this->translations->sync($category, (array) ($data['translations'] ?? []), ['name']);

            return $category;
        });
    }

    public function deleteCategory(MenuCategory $category): void
    {
        $category->delete();
    }

    /**
     * @param  array<string, mixed>  $data  menu_category_id, attributes, image_path,
     *                                      translations, allergens, dietary_labels, extra_group_ids
     *
     * @throws PlanLimitExceededException
     * @throws ValidationException
     */
    public function saveItem(array $data, ?MenuItem $item = null): MenuItem
    {
        return DB::transaction(function () use ($data, $item): MenuItem {
            $category = MenuCategory::query()->find($data['menu_category_id'] ?? $item?->menu_category_id)
                ?? throw ValidationException::withMessages(['menu_category_id' => __('validation.exists', ['attribute' => 'category'])]);

            if ($item === null) {
                DB::table('tenants')->where('id', $this->context->id())->lockForUpdate()->first();
                $this->gate->ensureCanCreate($this->context->tenant(), PlanFeature::MaxItems);

                $item = new MenuItem(['sort_order' => (int) $category->items()->max('sort_order') + 1]);
            } elseif ((int) $item->menu_category_id !== (int) $category->getKey()) {
                $item->sort_order = (int) $category->items()->max('sort_order') + 1;
            }

            $item->fill(Arr::only($data, self::ITEM_ATTRIBUTES));
            $item->menu_category_id = $category->getKey();

            if (array_key_exists('image_path', $data)) {
                $item->forceFill(['image_path' => $data['image_path']]);
            }

            $item->save();
            $this->translations->sync($item, (array) ($data['translations'] ?? []), ['name']);

            if (array_key_exists('allergens', $data)) {
                $this->syncEnumRows($item, 'allergens', 'allergen', Allergen::class, (array) $data['allergens']);
            }
            if (array_key_exists('dietary_labels', $data)) {
                $this->syncEnumRows($item, 'dietaryLabels', 'label', DietaryLabel::class, (array) $data['dietary_labels']);
            }
            if (array_key_exists('extra_group_ids', $data)) {
                $this->syncExtraGroups($item, (array) $data['extra_group_ids']);
            }

            return $item;
        });
    }

    public function deleteItem(MenuItem $item): void
    {
        $item->delete();
    }

    /**
     * @param  array<string, mixed>  $data  price, is_default, is_available, translations
     *
     * @throws ValidationException
     */
    public function saveVariant(MenuItem $item, array $data, ?MenuItemVariant $variant = null): MenuItemVariant
    {
        if (! isset($data['price']) || ! is_int($data['price']) || $data['price'] < 0) {
            throw ValidationException::withMessages(['price' => __('panels.app.money.invalid')]);
        }

        return DB::transaction(function () use ($item, $data, $variant): MenuItemVariant {
            $variant ??= $item->variants()->make(['sort_order' => (int) $item->variants()->max('sort_order') + 1]);
            $variant->fill(Arr::only($data, self::VARIANT_ATTRIBUTES));

            // At most one default per item: clear the others first (partial unique index).
            if ($variant->is_default) {
                $item->variants()->where('is_default', true)
                    ->when($variant->exists, fn ($query) => $query->whereKeyNot($variant->getKey()))
                    ->get()
                    ->each(fn (MenuItemVariant $other) => $other->update(['is_default' => false]));
            }

            $variant->save();
            $this->translations->sync($variant, (array) ($data['translations'] ?? []), ['name']);

            return $variant;
        });
    }

    public function deleteVariant(MenuItemVariant $variant): void
    {
        $variant->delete();
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     * @param  array<int, mixed>  $values
     */
    private function syncEnumRows(MenuItem $item, string $relation, string $column, string $enum, array $values): void
    {
        $wanted = collect($values)
            ->map(fn (mixed $value) => $value instanceof \BackedEnum ? $value->value : $value)
            ->filter(fn (mixed $value) => is_string($value) && $enum::tryFrom($value) !== null)
            ->unique()
            ->values();

        $existing = $item->{$relation}()->get();

        // Through the models, so observers (cache invalidation) fire.
        $existing->reject(fn ($row) => $wanted->contains($row->{$column}->value))->each->delete();

        $present = $existing->map(fn ($row) => $row->{$column}->value);
        foreach ($wanted->diff($present) as $value) {
            $item->{$relation}()->create([$column => $value]);
        }
    }

    /**
     * @param  array<int, mixed>  $ids
     */
    private function syncExtraGroups(MenuItem $item, array $ids): void
    {
        // Only this tenant's groups (tenant-scoped query); unknown ids are dropped.
        $valid = ExtraGroup::query()->whereKey(array_filter($ids, 'is_numeric'))->pluck('id')->all();
        $sync = [];

        foreach (array_values(array_intersect(array_map('intval', $ids), $valid)) as $position => $id) {
            $sync[$id] = ['sort_order' => $position];
        }

        $item->extraGroups()->sync($sync);
    }
}
