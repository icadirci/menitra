<?php

declare(strict_types=1);

namespace App\Services\Menu;

use App\Enums\ExtraSelectionType;
use App\Models\Extra;
use App\Models\ExtraGroup;
use App\Models\Location;
use App\Models\LocationMenu;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\Tenant;
use App\Support\Media\TenantMediaPath;
use App\Support\Menu\Data\CategoryData;
use App\Support\Menu\Data\ExtraData;
use App\Support\Menu\Data\ExtraGroupData;
use App\Support\Menu\Data\HoursData;
use App\Support\Menu\Data\ItemData;
use App\Support\Menu\Data\LabelData;
use App\Support\Menu\Data\LocaleData;
use App\Support\Menu\Data\LocationInfo;
use App\Support\Menu\Data\LocationSummary;
use App\Support\Menu\Data\MenuData;
use App\Support\Menu\Data\MenuSnapshot;
use App\Support\Menu\Data\MenuSummary;
use App\Support\Menu\Data\PriceData;
use App\Support\Menu\Data\SpecialHoursData;
use App\Support\Menu\Data\TenantBrand;
use App\Support\Menu\Data\VariantData;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the allow-listed, locale-resolved snapshot DTOs from the database.
 * Runs inside the tenant's TenantContext (PublicMenuService sets it). Only
 * visible and published content enters; everything is loaded with a fixed
 * set of eager-loaded queries.
 */
final class MenuSnapshotBuilder
{
    public function __construct(private readonly MenuUrlGenerator $urls) {}

    public function brand(Tenant $tenant, string $locale, bool $showPlatformBranding): TenantBrand
    {
        $translation = $tenant->load('translations')->translate($locale);

        return new TenantBrand(
            name: (string) ($translation?->name ?? $tenant->slug),
            tagline: $translation?->tagline,
            description: $translation?->description,
            logoUrl: $this->mediaUrl($tenant->logo_path),
            coverUrl: $this->mediaUrl($tenant->cover_path),
            showPlatformBranding: $showPlatformBranding,
        );
    }

    /**
     * @param  list<string>  $locales
     * @return list<LocaleData>
     */
    public function locales(array $locales): array
    {
        return array_map(
            fn (string $code): LocaleData => new LocaleData($code, (string) config("locales.supported.{$code}", strtoupper($code))),
            $locales,
        );
    }

    /**
     * Active branches of the tenant, in order.
     *
     * @return list<LocationSummary>
     */
    public function locationSummaries(Tenant $tenant, string $locale): array
    {
        return Location::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Location $location): LocationSummary => new LocationSummary(
                id: (int) $location->getKey(),
                slug: $location->slug,
                name: (string) ($location->translate($locale)?->name ?? $location->slug),
                address: $this->address($location),
                url: $this->urls->location($tenant->slug, $location->slug),
            ))
            ->values()
            ->all();
    }

    /**
     * Location landing data (no menu content yet).
     *
     * @param  list<string>  $locales
     */
    public function location(Tenant $tenant, Location $location, string $locale, array $locales, string $version, bool $showPlatformBranding): MenuSnapshot
    {
        $location->load(['translations', 'openingHours', 'specialHours' => fn (HasMany $query) => $query
            ->whereDate('date', '>=', CarbonImmutable::now($location->timezone)->subDay()->toDateString())
            ->whereDate('date', '<=', CarbonImmutable::now($location->timezone)->addDays(30)->toDateString()),
        ]);

        $translation = $location->translate($locale);

        $info = new LocationInfo(
            slug: $location->slug,
            name: (string) ($translation?->name ?? $location->slug),
            description: $translation?->description,
            address: $this->address($location),
            phone: $location->phone,
            whatsapp: $location->whatsapp,
            email: $location->email,
            mapUrl: $this->mapUrl($location),
            timezone: $location->timezone,
            coverUrl: $this->mediaUrl($location->cover_path),
            theme: $location->theme?->value,
            url: $this->urls->location($tenant->slug, $location->slug),
            weeklyHours: $location->openingHours
                ->map(fn ($row): HoursData => new HoursData($row->day_of_week, substr((string) $row->opens_at, 0, 5), substr((string) $row->closes_at, 0, 5)))
                ->values()->all(),
            specialHours: $location->specialHours
                ->map(fn ($row): SpecialHoursData => new SpecialHoursData(
                    $row->date->toDateString(),
                    $row->is_closed,
                    $row->opens_at === null ? null : substr((string) $row->opens_at, 0, 5),
                    $row->closes_at === null ? null : substr((string) $row->closes_at, 0, 5),
                ))
                ->values()->all(),
        );

        $menus = LocationMenu::query()
            ->where('location_id', $location->getKey())
            ->where('is_active', true)
            ->whereHas('menu', fn (Builder $query) => $query->where('is_published', true))
            ->with(['menu.translations', 'hours'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (LocationMenu $attachment): MenuSummary => new MenuSummary(
                id: (int) $attachment->menu->getKey(),
                slug: $attachment->menu->slug,
                name: (string) ($attachment->menu->translate($locale)?->name ?? $attachment->menu->slug),
                description: $attachment->menu->translate($locale)?->description,
                type: $attachment->menu->type->value,
                typeLabel: $attachment->menu->type->getLabel(),
                url: $this->urls->menu($tenant->slug, $location->slug, $attachment->menu->slug),
                servingHours: $attachment->hours
                    ->map(fn ($row): HoursData => new HoursData($row->day_of_week, substr((string) $row->starts_at, 0, 5), substr((string) $row->ends_at, 0, 5)))
                    ->values()->all(),
            ))
            ->values()
            ->all();

        return new MenuSnapshot(
            version: $version,
            locale: $locale,
            availableLocales: $this->locales($locales),
            brand: $this->brand($tenant, $locale, $showPlatformBranding),
            location: $info,
            menus: $menus,
        );
    }

    /**
     * Visible content of a menu at a location, with that branch's sold-out facts.
     */
    public function menu(Tenant $tenant, Location $location, Menu $menu, string $locale): MenuData
    {
        $states = $location->itemStates()->where('is_sold_out', true)->get()->keyBy('menu_item_id');

        $menu->load([
            'translations',
            'categories' => fn (HasMany $query) => $query->where('is_visible', true),
            'categories.translations',
            'categories.items' => fn (HasMany $query) => $query->where('is_visible', true),
            'categories.items.translations',
            'categories.items.variants.translations',
            'categories.items.allergens',
            'categories.items.dietaryLabels',
            'categories.items.extraGroups.translations',
            'categories.items.extraGroups.extras.translations',
        ]);

        $currency = $tenant->currency;
        $featured = [];

        $categories = $menu->categories
            ->map(function (MenuCategory $category) use ($locale, $currency, $states, &$featured): CategoryData {
                $items = $category->items
                    ->map(fn (MenuItem $item): ItemData => $this->item($item, $locale, $currency, $states->get($item->getKey())))
                    ->values()
                    ->all();

                foreach ($category->items as $index => $item) {
                    if ($item->is_featured) {
                        $featured[] = $items[$index];
                    }
                }

                $translation = $category->translate($locale);

                return new CategoryData(
                    id: (int) $category->getKey(),
                    anchor: 'c'.$category->getKey(),
                    name: (string) ($translation?->name ?? ''),
                    description: $translation?->description,
                    imageUrl: $this->mediaUrl($category->image_path),
                    items: $items,
                );
            })
            ->filter(fn (CategoryData $category): bool => $category->items !== [])
            ->values()
            ->all();

        $translation = $menu->translate($locale);

        return new MenuData(
            slug: $menu->slug,
            name: (string) ($translation?->name ?? $menu->slug),
            description: $translation?->description,
            type: $menu->type->value,
            categories: $categories,
            featured: $featured,
        );
    }

    private function item(MenuItem $item, string $locale, string $currency, mixed $state): ItemData
    {
        $translation = $item->translate($locale);
        $variants = $item->variants
            ->map(fn (MenuItemVariant $variant): VariantData => new VariantData(
                publicId: $variant->public_id,
                name: (string) ($variant->translate($locale)?->name ?? ''),
                price: $this->price($variant->price, $currency, $locale),
                isDefault: $variant->is_default,
                isAvailable: $variant->is_available,
            ))
            ->values()
            ->all();

        $fromPrice = null;
        if ($variants !== []) {
            $available = array_filter($variants, fn (VariantData $v): bool => $v->isAvailable);
            $prices = array_map(fn (VariantData $v): int => $v->price->minor, $available !== [] ? $available : $variants);
            $fromPrice = $this->price(min($prices), $currency, $locale);
        }

        return new ItemData(
            publicId: $item->public_id,
            name: (string) ($translation?->name ?? ''),
            description: $translation?->description,
            imageUrl: $this->mediaUrl($item->image_path),
            price: $variants === [] && $item->price !== null ? $this->price($item->price, $currency, $locale) : null,
            fromPrice: $fromPrice,
            isFeatured: $item->is_featured,
            isPopular: $item->is_popular,
            isAvailable: $item->is_available,
            soldOutIndefinitely: $state !== null && $state->sold_out_until === null,
            soldOutUntil: $state?->sold_out_until?->getTimestamp(),
            calories: $item->calories,
            preparationMinutes: $item->preparation_minutes,
            allergens: $item->allergens
                ->map(fn ($row): LabelData => new LabelData($row->allergen->value, $row->allergen->label($locale)))
                ->sortBy('label')->values()->all(),
            dietaryLabels: $item->dietaryLabels
                ->map(fn ($row): LabelData => new LabelData($row->label->value, $row->label->label($locale)))
                ->values()->all(),
            variants: $variants,
            extraGroups: $item->extraGroups
                ->map(fn (ExtraGroup $group): ExtraGroupData => new ExtraGroupData(
                    name: (string) ($group->translate($locale)?->name ?? ''),
                    selectionType: $group->selection_type->value,
                    minSelect: $group->min_select,
                    maxSelect: $group->selection_type === ExtraSelectionType::Single ? 1 : $group->max_select,
                    isRequired: $group->is_required,
                    extras: $group->extras
                        ->map(fn (Extra $extra): ExtraData => new ExtraData(
                            publicId: $extra->public_id,
                            name: (string) ($extra->translate($locale)?->name ?? ''),
                            priceDelta: $extra->price_delta === 0 ? null : $this->price($extra->price_delta, $currency, $locale),
                            isAvailable: $extra->is_available,
                        ))
                        ->values()->all(),
                ))
                ->values()->all(),
        );
    }

    private function price(int $minor, string $currency, string $locale): PriceData
    {
        return new PriceData($minor, Money::fromMinor($minor, $currency)->format($locale, omitZeroFraction: true));
    }

    private function address(Location $location): ?string
    {
        $locality = implode(' / ', array_filter([$location->district, $location->city]));
        $address = implode(', ', array_filter([$location->address_line, $locality]));

        return $address === '' ? null : $address;
    }

    private function mapUrl(Location $location): ?string
    {
        if ($location->latitude !== null && $location->longitude !== null) {
            return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($location->latitude.','.$location->longitude);
        }

        $address = $this->address($location);

        return $address === null ? null : 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address);
    }

    private function mediaUrl(?string $path): ?string
    {
        return $path === null || $path === '' ? null : Storage::disk(TenantMediaPath::DISK)->url($path);
    }
}
