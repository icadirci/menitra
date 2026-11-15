<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Enums\MenuTheme;
use App\Services\Menu\MenuCacheKeys;
use App\Services\Menu\MenuUrlGenerator;
use App\Tenancy\ResolvedTenantFactory;

/**
 * Data of the theme showcase page: every theme with its default palette and,
 * when its demo business (config marketing.showcase) resolves and is online,
 * the business name and its live public menu URL. Resolution goes through
 * the cached public resolver (ResolvedTenantFactory, qrmenu:slug:*), so a warm
 * page runs no query and exposes only what the public menu already shows.
 */
final class ThemeShowcase
{
    public function __construct(
        private readonly ResolvedTenantFactory $tenants,
        private readonly MenuUrlGenerator $urls,
    ) {}

    /**
     * @return list<ShowcaseTheme>
     */
    public function themes(string $locale): array
    {
        $demos = (array) config('marketing.showcase', []);

        return array_map(function (MenuTheme $theme) use ($demos, $locale): ShowcaseTheme {
            $slug = $demos[$theme->value] ?? null;
            $tenant = is_string($slug) && $slug !== '' ? $this->tenants->bySlug($slug, MenuCacheKeys::slug($slug)) : null;
            $online = $tenant !== null && $tenant->isOnline();

            return new ShowcaseTheme(
                theme: $theme,
                colors: $theme->defaults()['colors'],
                venue: $online ? $tenant->name($locale) : null,
                url: $online ? $this->urls->tenant($tenant->slug).'?lang='.(in_array($locale, $tenant->locales, true) ? $locale : $tenant->defaultLocale) : null,
            );
        }, array_values(array_filter(MenuTheme::cases(), fn (MenuTheme $theme): bool => $theme->isBuilt())));
    }
}
