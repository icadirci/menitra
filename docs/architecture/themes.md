# Menu Themes

> Status: **Approved — v0.2**. Themes are presentation layers only. All themes consume the same data contract and share all backend logic.

## Contents

- [Principles](#principles)
- [Theme selection](#theme-selection)
- [Theme registry](#theme-registry)
- [Theme inputs](#theme-inputs)
- [File layout](#file-layout)
- [Shared components](#shared-components)
- [Styling and customisation](#styling-and-customisation)
- [Enforcing independence](#enforcing-independence)
- [Adding a theme](#adding-a-theme)

## Principles

- **One controller, one service, one snapshot for every theme.** No theme has its own routes, queries, services or business rules.
- A theme may change colors, typography, layout, product card style, category navigation, header and cover presentation, and product detail presentation.
- A theme may **not**:
  - query data
  - call services, models, `Cache`, `DB`, `auth()` or `request()`
  - hard-code business information, products or user-facing strings
  - decide visibility, availability or pricing

## Theme selection

- Brand identity is set **per tenant** (`tenants.theme`). A location can override it (`locations.theme`, nullable).
- Effective theme = `location.theme ?? tenant.theme`.
- All menus at one location use the same theme, so breakfast and dinner never look different.
- Per-theme customisation lives in `theme_settings` (unique per tenant and theme). Switching back to a theme restores the settings it had.
- Plan gating: if the effective theme requires a feature the current plan lacks (`premium_themes`), `ThemeRenderer` falls back to `classic`. **Settings are not deleted.**

## Theme registry

Themes are code (Blade and CSS), so they're registered in code rather than in a table. The `App\Enums\MenuTheme` string-backed enum is the registry:

| Case | Character (working description) | Design reference (`docs/design/source/`) |
|---|---|---|
| `classic` | Reference theme. Clean list layout with a sticky category bar; warm Turkish-café palette, arched images | `MenuKonak`, `DetailKonak` (Young Serif / Figtree) |
| `minimal` | Typography-first, lots of whitespace, few images | `MenuNoon`, `DetailNoon` (DM Sans / DM Mono) |
| `bistro` | Dark, elegant, serif headings | `MenuArdoise`, `DetailArdoise` (Cormorant Garamond / Jost) |
| `cafe` | Warm palette, rounded product cards | `MenuKiln`, `DetailKiln` (Manrope) |
| `grid` | Photo-first grid layout, soft pastel palette | `MenuPetal`, `DetailPetal` (Fraunces / Nunito Sans) |

The venue names in the design files (Kiln Coffee, Maison Ardoise, noon., Petal Bakehouse, Kahve Konağı) are sample content only; they become demo data, never theme keys or template strings (decided 2026-11-15).

Each case provides:

| Method | Returns |
|---|---|
| `view()` | `themes.{key}.menu` |
| `locationView()` | `themes.{key}.location` |
| `stylesheet()` | `resources/css/themes/{key}.css` (Vite entry) |
| `defaults()` | Default settings (colors, font pair, layout options) |
| `settingsRules()` | Validation rules for `theme_settings.settings` |
| `requiredFeature()` | `?PlanFeature`, e.g. `premium_themes` |

## Theme inputs

A theme view receives exactly these three variables:

| Variable | Type | Source |
|---|---|---|
| `$menu` | `MenuSnapshot` | Cached, locale-resolved, allow-listed data ([menu-system.md](menu-system.md#menusnapshot-contract)) |
| `$live` | `LiveMenuState` | Per request: open now, serving now, effective sold-out (`$live->isSoldOut($item)`) |
| `$theme` | `ThemeSettings` | Merged `defaults()` and tenant `theme_settings`, validated |

UI strings come from `__('menu.*')`. No other data source is allowed.

## File layout

```
resources/
  views/
    layouts/menu.blade.php                 shared <head>, meta, CSS variables, Vite tags
    components/menu/                       shared building blocks (below)
    menu/location-index.blade.php          tenant with several locations
    menu/unavailable.blade.php             platform page, not themed
    themes/{key}/
      menu.blade.php                       menu page
      location.blade.php                   location landing page
      components/                          optional overrides of components/menu/*
  css/
    menu-base.css                          shared resets, base tokens, component base styles
    themes/{key}.css                       theme-specific styling
  js/
    menu.js                                Alpine only (modal, search, tabs, language switcher)
```

## Shared components

`resources/views/components/menu/`:

| Component | Purpose |
|---|---|
| `category-nav` | Sticky or scrolling category navigation |
| `menu-switcher` | Switch between breakfast, lunch, … with a "now serving" highlight |
| `item-card` | Product card (name, price / from price, image, badges, sold-out state) |
| `item-modal` | Product detail (Alpine): description, variants, extras, allergens |
| `variant-list` | Variant names and prices |
| `extras-list` | Extra groups with rules and price deltas (display only in v1) |
| `price` | Formatted `Money` / "from" price |
| `allergen-badges`, `dietary-badges` | Translated enum badges |
| `opening-hours` | Weekly and special hours with the open-now state |
| `language-switcher` | `?lang=` links for the tenant's enabled locales |
| `search` | Client-side filter over rendered items |
| `branding-footer` | "Powered by Restano", hidden when the plan includes `remove_branding` |

To override a component, a theme places a variant in `themes/{key}/components/` and uses it in its own views. It must take the same props.

## Styling and customisation

- Tenant settings are rendered as CSS custom properties on the menu root element:
  - `--menu-primary`
  - `--menu-accent`
  - `--menu-bg`
  - `--menu-text`
  - `--menu-font-heading`
  - `--menu-font-body`
- Theme CSS uses these variables through Tailwind 4 (`@theme` / `var()`). There is one stylesheet per theme and no per-tenant CSS builds.
- Fonts: a fixed, curated set of font pairs per theme, self-hosted, never loaded from arbitrary user input.
- Colors are validated as hex values by `settingsRules()`. Contrast is checked against the background to meet accessibility requirements.
- Mobile-first: every theme must work at 320px wide and with large text settings.

## Enforcing independence

1. **Architecture test** (`tests/Architecture/ThemeIndependenceTest`): scans `resources/views/themes/**` and fails on `App\Models`, `App\Services`, `DB::`, `Cache::`, `auth(`, `request(`, `@php` blocks and `{!!`.
2. **Contract test**: renders every `MenuTheme::cases()` theme, both the menu and the location view, from the same fake `MenuSnapshot`, with a query listener attached. It asserts **zero database queries** and a successful render.
3. **Content test**: every theme renders all snapshot features (variants, extras, allergens, sold-out, featured, popular, hours, language switcher) or explicitly delegates to a shared component that does.

## Adding a theme

1. Add a case to `MenuTheme` with `defaults()`, `settingsRules()` and `requiredFeature()`.
2. Create `resources/views/themes/{key}/menu.blade.php` and `location.blade.php`, composing the shared components.
3. Create `resources/css/themes/{key}.css` and register it as a Vite input.
4. Add translations to `lang/*/menu.php` for any new UI strings.
5. Run the theme test suite. No controller, service or schema change should be needed. If one seems necessary, it's an architectural change and needs review.
