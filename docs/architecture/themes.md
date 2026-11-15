# Menu Themes

> Status: **Approved — v0.4** (2026-11-15, B9: `theme_settings` with colours, font pair and layout options, contrast check, "Theme & appearance" page, owner-only branch override, themes without views listed as "coming soon"). v0.3 (2026-11-16: `classic` built in Phase 6; a theme without views falls back to `classic` until Phase 7). Themes are presentation layers only. All themes consume the same data contract and share all backend logic.

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
- Per-theme customisation lives in `theme_settings` (unique per tenant and theme). Switching back to a theme restores the settings it had. A location override uses the tenant's settings of that theme; there are no per-location settings. A missing row means the theme's defaults; registration writes the `classic` row with defaults.
- Plan gating: if the effective theme requires a feature the current plan lacks (`premium_themes`), `ThemeRenderer` falls back to `classic`. **Settings are not deleted.**

### Managing themes (business panel, B9)

All writes go through `App\Services\Themes\ThemeService`; the panel only calls it.

| Action | Where | Who | Server rules |
|---|---|---|---|
| Choose the tenant theme and edit the selected theme's settings | **Theme & appearance** page (`ManageTheme`, `/app/{tenant}/theme`) | Owners (`TenantPolicy::manageSettings`, in `canAccess()` and again in `save()` / reset) | Theme must be built; a premium theme needs `premium_themes` when it is **newly** chosen; settings pass `MenuTheme::validator()` |
| Branch override | Location form, "Appearance" section | Owners (`LocationPolicy::setTheme`); managers and branch staff see the field read-only, it is not dehydrated for them and a submitted value from them is a 403 | Same theme rules as above (`ThemeService::setLocationTheme`) |

- **Built themes only.** `MenuTheme::isBuilt()` is true when both views exist. Until Phase 7 the other four themes are listed as **"coming soon"** (disabled option) and the server refuses them; they become selectable automatically when their views ship.
- **Plan gating follows `FeatureGate`:** choosing a premium theme is a creation-like action (`ensureCanCreate(premium_themes)`, shown as the "not included" notification). Keeping or editing the theme already in use is never blocked, and nothing is deleted when the plan changes; the page shows a warning while the selected theme is rendered as `classic`.
- Switching the theme in the form loads that theme's saved settings (or defaults). "Reset to defaults" only refills the form; nothing changes until saved.

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
| `defaults()` | Default settings: `colors`, `font_pair`, `layout` (must pass `validator()`, enforced by a test for every case) |
| `fontPairs()` | Offered `MenuFontPair` cases (the stylesheet declares their `@font-face`) |
| `layoutChoices()` | Allowed values of non-boolean layout options |
| `contrastPairs()` | Colour pairs `[foreground, background]` that must reach WCAG AA |
| `settingsRules()` | Validation rules for `theme_settings.settings` |
| `validator()` / `normalize()` | Rules + contrast check; canonical form (known keys only, upper-case hex, real booleans) |
| `isBuilt()` | Both views exist (selectable) |
| `label()` | Panel label (`panels.app.theme.themes.*`) |
| `requiredFeature()` | `?PlanFeature`, e.g. `premium_themes` |

## Theme inputs

A theme view receives exactly these three variables:

| Variable | Type | Source |
|---|---|---|
| `$menu` | `MenuSnapshot` | Cached, locale-resolved, allow-listed data ([menu-system.md](menu-system.md#menusnapshot-contract)) |
| `$live` | `LiveMenuState` | Per request: open now, serving now, effective sold-out (`$live->isSoldOut($item)`) |
| `$theme` | `ThemeSettings` | Merged `defaults()` and tenant `theme_settings`, validated again (`ThemeSettings::fromStored()`: a section that fails the current rules falls back to its defaults). Saved settings travel in the cached `ResolvedTenant`, so rendering runs no query. Layout options are read with `$theme->option('name')` |

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
  - `--menu-surface`, `--menu-muted`, `--menu-ornament` (cards, secondary text, decorative details)
- Theme CSS uses these variables through Tailwind 4 (`@theme` / `var()`). There is one stylesheet per theme and no per-tenant CSS builds.
- Fonts: a fixed, curated set of font pairs per theme (`MenuFontPair`: `young_serif_figtree`, `bricolage_inter`, `system`), self-hosted, never loaded from arbitrary user input. The font stacks come only from the enum and are emitted as `--menu-font-heading` / `--menu-font-body` on the menu root, overriding the theme stylesheet's defaults; a `@font-face` is downloaded only when its font is used.
- Colors are validated as `#RRGGBB` by `settingsRules()`. Contrast (`App\Support\ColorContrast`, WCAG 2.x) must reach **4.5:1** for `text`, `muted` and `primary` on `bg` and `surface`, `accent` on `bg`, and `surface` text on `accent` (badges). `ornament` is decorative and not checked.
- Settings shape (`theme_settings.settings`, jsonb):

  ```json
  {"colors": {"primary": "#23443A", "accent": "#9C4A2F", "bg": "#F4EBDD", "surface": "#FBF5EA",
              "text": "#2B1D16", "muted": "#6B5446", "ornament": "#C49A45"},
   "font_pair": "young_serif_figtree",
   "layout": {"show_images": true, "show_featured": true, "image_shape": "arch"}}
  ```

- Layout options are per theme. `classic`: `show_images` (pictures in the item list), `show_featured` (featured strip), `image_shape` (`arch` | `rounded`).
- Mobile-first: every theme must work at 320px wide and with large text settings.

## Enforcing independence

1. **Architecture test** (`tests/Architecture/ThemeIndependenceTest`): scans `resources/views/themes/**` and fails on `App\Models`, `App\Services`, `DB::`, `Cache::`, `auth(`, `request(`, `@php` blocks and `{!!`.
2. **Contract test**: renders every `MenuTheme::cases()` theme, both the menu and the location view, from the same fake `MenuSnapshot`, with a query listener attached. It asserts **zero database queries** and a successful render.
3. **Content test**: every theme renders all snapshot features (variants, extras, allergens, sold-out, featured, popular, hours, language switcher) or explicitly delegates to a shared component that does.

## Adding a theme

1. Add a case to `MenuTheme` with `defaults()` (passing the contrast check), `fontPairs()`, `layoutChoices()` and `requiredFeature()`; add panel labels for its name, description and any new layout option in `lang/*/panels.php`.
2. Create `resources/views/themes/{key}/menu.blade.php` and `location.blade.php`, composing the shared components.
3. Create `resources/css/themes/{key}.css` (with `@font-face` for every offered font pair) and register it as a Vite input. Once the views exist the theme is selectable in the panel.
4. Add translations to `lang/*/menu.php` for any new UI strings.
5. Run the theme test suite. No controller, service or schema change should be needed. If one seems necessary, it's an architectural change and needs review.
