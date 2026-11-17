# Menu Themes

> Status: **Approved — v0.5** (2026-11-15, Phase 7 — autorun: `minimal`, `bistro`, `cafe`, `grid` built; font pairs `inter` and `figtree`; shared detail components). v0.4 (2026-11-15, B9: `theme_settings` with colours, font pair and layout options, contrast check, "Theme & appearance" page, owner-only branch override, themes without views listed as "coming soon"). v0.3 (2026-11-16: `classic` built in Phase 6; a theme without views falls back to `classic` until Phase 7). Themes are presentation layers only. All themes consume the same data contract and share all backend logic.

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
- Basic themes: `classic` and `minimal` are included in every plan; `bistro`, `cafe` and `grid` need `premium_themes` (`MenuTheme::requiredFeature()`).
- Plan gating: if the effective theme requires a feature the current plan lacks (`premium_themes`), `ThemeRenderer` falls back to `classic`. **Settings are not deleted.**

### Managing themes (business panel, B9)

All writes go through `App\Services\Themes\ThemeService`; the panel only calls it.

| Action | Where | Who | Server rules |
|---|---|---|---|
| Choose the tenant theme and edit the selected theme's settings | **Theme & appearance** page (`ManageTheme`, `/app/{tenant}/theme`) | Owners (`TenantPolicy::manageSettings`, in `canAccess()` and again in `save()` / reset) | Theme must be built; a premium theme needs `premium_themes` when it is **newly** chosen; settings pass `MenuTheme::validator()` |
| Branch override | Location form, "Appearance" section | Owners (`LocationPolicy::setTheme`); managers and branch staff see the field read-only, it is not dehydrated for them and a submitted value from them is a 403 | Same theme rules as above (`ThemeService::setLocationTheme`) |

- **Built themes only.** `MenuTheme::isBuilt()` is true when both views exist. A theme without views is listed as **"coming soon"** (disabled option) and the server refuses it. Since Phase 7 (2026-11-15) all five themes are built and selectable; the rule stays for future themes (tests simulate an unbuilt theme with a view finder that hides its views).
- **Plan gating follows `FeatureGate`:** choosing a premium theme is a creation-like action (`ensureCanCreate(premium_themes)`, shown as the "not included" notification). Keeping or editing the theme already in use is never blocked, and nothing is deleted when the plan changes; the page shows a warning while the selected theme is rendered as `classic`.
- Switching the theme in the form loads that theme's saved settings (or defaults). "Reset to defaults" only refills the form; nothing changes until saved.

## Theme registry

Themes are code (Blade and CSS), so they're registered in code rather than in a table. The `App\Enums\MenuTheme` string-backed enum is the registry:

| Case | Character | Design reference (`docs/design/source/`) | Default font pair · offered | Layout options |
|---|---|---|---|---|
| `classic` | Reference theme. Clean list layout with a sticky category bar; warm Turkish-café palette, arched images | `MenuKonak`, `DetailKonak` (Young Serif / Figtree) | `young_serif_figtree` · + `bricolage_inter`, `system` | `show_images`, `show_featured`, `image_shape` (`arch` \| `rounded`) |
| `minimal` | Typography-first, white, hairline rows, small thumbnails, monospace prices | `MenuNoon`, `DetailNoon` (DM Sans / DM Mono) | `inter` · + `bricolage_inter`, `system` | `show_images`, `show_featured` |
| `bistro` | Dark and elegant: framed cover, centred serif title, italic descriptions, round thumbnails, dotted price leaders, "signature" cards for featured items | `MenuArdoise`, `DetailArdoise` (Cormorant Garamond / Jost) | `system` (Georgia + system sans) · + `young_serif_figtree`, `bricolage_inter` | `show_images`, `show_featured` |
| `cafe` | Warm palette, floating venue card over the cover, rounded product cards (text left, picture right), horizontal featured cards, rounded detail sheet | `MenuKiln`, `DetailKiln` (Manrope) | `figtree` · + `bricolage_inter`, `young_serif_figtree`, `system` | `show_images`, `show_featured` |
| `grid` | Photo-first two-column grid, soft pastel palette, rounded cover with overlapping logo, detail as a floating card | `MenuPetal`, `DetailPetal` (Fraunces / Nunito Sans) | `young_serif_figtree` · + `figtree`, `bricolage_inter`, `system` | `show_featured`, `card_ratio` (`square` \| `portrait`) |

**Decision (autorun, 2026-11-15) — fonts:** the design's fonts for the four new themes (DM Sans/Mono, Cormorant Garamond/Jost, Manrope, Fraunces/Nunito Sans) are not in `resources/fonts/`, and adding font files was not part of the unattended run. The themes therefore use only the already self-hosted families (Young Serif, Figtree, Bricolage Grotesque, Inter) plus system stacks; `MenuFontPair` gained `inter` (Inter/Inter) and `figtree` (Figtree/Figtree). `minimal` prints prices in the system monospace stack. Adding the design fonts later is a stylesheet + enum change only (new `MenuFontPair` cases, `@font-face` in the theme file).

**Decision (autorun) — colours:** each theme's defaults follow its design palette and pass `MenuTheme::validator()` incl. the contrast check (`ThemeManagementTest` checks every case). Text is only ever placed on `bg`, `surface`, `primary` or `accent` (or a light mix of `text` into `surface`), so every text pair is covered by `contrastPairs()`; `ornament` is used only for decoration (covers, dots, borders, placeholders).

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
| `item-details` | Body of the detail sheet: variants, extra groups (Alpine display-only choices), preparation / calories / allergens, dietary labels (classes `menu-detail*`, `menu-choice`, `menu-fact`). Used by the four Phase 7 themes |
| `item-status` | Popular and effective sold-out / unavailable badges (`$live->isSoldOut()`) |
| `open-state` | "Open · until …" / "Closed · opens at …" from `LiveMenuState` |
| `contact-actions` | WhatsApp or call, and directions links of a location |

Implemented so far (2026-11-15): `price`, `badges`, `opening-hours`, `language-switcher`, `branding-footer`, `item-details`, `item-status`, `open-state`, `contact-actions`. Category navigation, menu switcher, item cards and the sheet shell are per theme (they carry most of each identity); `menu-base.css` holds the shared structure (`menu-sheet`, `menu-detail`, `menu-choices`, `menu-facts`, `menu-actions`).

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
- Fonts: a fixed, curated set of font pairs per theme (`MenuFontPair`: `young_serif_figtree`, `bricolage_inter`, `inter`, `figtree`, `system`), self-hosted, never loaded from arbitrary user input. The font stacks come only from the enum and are emitted as `--menu-font-heading` / `--menu-font-body` on the menu root, overriding the theme stylesheet's defaults; a `@font-face` is downloaded only when its font is used.
- Colors are validated as `#RRGGBB` by `settingsRules()`. Contrast (`App\Support\ColorContrast`, WCAG 2.x) must reach **4.5:1** for `text`, `muted` and `primary` on `bg` and `surface`, `accent` on `bg`, and `surface` text on `accent` (badges). `ornament` is decorative and not checked.
- Settings shape (`theme_settings.settings`, jsonb):

  ```json
  {"colors": {"primary": "#23443A", "accent": "#9C4A2F", "bg": "#F4EBDD", "surface": "#FBF5EA",
              "text": "#2B1D16", "muted": "#6B5446", "ornament": "#C49A45"},
   "font_pair": "young_serif_figtree",
   "layout": {"show_images": true, "show_featured": true, "image_shape": "arch", "category_layout": "list"}}
  ```

- Layout options are per theme (see the [registry](#theme-registry)). `classic`: `show_images` (pictures in the item list), `show_featured` (featured strip), `image_shape` (`arch` | `rounded`); `grid`: `card_ratio` (`square` | `portrait`) instead of `show_images`, since it is photo-first (items without a picture get a pastel placeholder).
- **Category display (`category_layout`, every theme, added 2026-11-17):** `list` (default — all categories on one page with the category bar, the original behaviour) or `tiles` (the page starts with a two-column grid of category cards using the category picture with a dark overlay, or the theme's `primary`/`surface` without one; a tap shows only that category's items with an "All categories" button). The cards are the shared component `x-menu.category-tiles` styled in `menu-base.css` (themes only set `--menu-tile-radius`); the switching is client-side in `menuPage` (`#c{id}` is a history entry, so the phone's back button returns to the cards; a search always covers every category). Settings stored before the option existed, or holding an unknown value, render as `list`. Theme views read it through `ThemeSettings::categoryTiles()`.
- Mobile-first: every theme must work at 320px wide and with large text settings.

## Enforcing independence

1. **Architecture test** (`tests/Architecture/ThemeIndependenceTest`): scans `resources/views/themes/**` and fails on `App\Models`, `App\Services`, `DB::`, `Cache::`, `auth(`, `request(`, `@php` blocks and `{!!`.
2. **Contract test**: renders every `MenuTheme::cases()` theme, both the menu and the location view, from the same fake `MenuSnapshot`, with a query listener attached. It asserts that **all five are built**, **zero database queries** and a successful render — also with every layout option flipped. A stylesheet test checks that each theme file is scoped, uses `@reference`, and declares `@font-face` for every font pair it offers.
3. **Content test**: every theme renders all snapshot features (variants, extras, allergens, sold-out, featured, popular, hours, language switcher) or explicitly delegates to a shared component that does.

## Adding a theme

1. Add a case to `MenuTheme` with `defaults()` (passing the contrast check), `fontPairs()`, `layoutChoices()` and `requiredFeature()`; add panel labels for its name, description and any new layout option in `lang/*/panels.php`.
2. Create `resources/views/themes/{key}/menu.blade.php` and `location.blade.php`, composing the shared components.
3. Create `resources/css/themes/{key}.css` (with `@font-face` for every offered font pair) and register it as a Vite input. Once the views exist the theme is selectable in the panel.
4. Add translations to `lang/*/menu.php` for any new UI strings.
5. Run the theme test suite. No controller, service or schema change should be needed. If one seems necessary, it's an architectural change and needs review.
