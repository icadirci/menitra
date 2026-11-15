<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * The single data contract every theme consumes (docs/architecture/menu-system.md).
 * Plain readonly values only: cached as is, no models, no hidden fields.
 * `menu` is null on the location landing page.
 */
final readonly class MenuSnapshot
{
    /**
     * @param  list<LocaleData>  $availableLocales
     * @param  list<MenuSummary>  $menus
     */
    public function __construct(
        public string $version,
        public string $locale,
        public array $availableLocales,
        public TenantBrand $brand,
        public LocationInfo $location,
        public array $menus,
        public ?MenuData $menu = null,
    ) {}

    public function withMenu(MenuData $menu): self
    {
        return new self($this->version, $this->locale, $this->availableLocales, $this->brand, $this->location, $this->menus, $menu);
    }

    public function findMenu(string $slug): ?MenuSummary
    {
        foreach ($this->menus as $menu) {
            if ($menu->slug === $slug) {
                return $menu;
            }
        }

        return null;
    }
}
