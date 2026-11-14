# Frontend Development Rules

## General
- Use Laravel Blade, Tailwind CSS and Alpine.js.
- Use Vite for asset compilation.
- Do not use React, Vue, Inertia, Livewire or Bootstrap.
- **Exception — Filament panels:** Livewire is permitted only as a Filament dependency inside the `/admin` and `/app` panels. Do not write Livewire components or load Livewire/Filament assets in the marketing site, auth pages or QR menus.
- Build reusable Blade components.
- Use Blade layouts and partials.
- Prioritize mobile-first responsive design.
- Keep JavaScript lightweight.
- Use Alpine.js for small UI interactions.
- Maintain consistent typography, colors, spacing and component styles.
- Avoid inline JavaScript when reusable components are more appropriate.
- Optimize images and use lazy loading where appropriate (with explicit dimensions).
- Ensure accessibility and readable contrast.
- Never hardcode menu products, tenant information or user-facing strings inside templates; use `__()` with `lang/tr` and `lang/en`.

## Bundles
- Marketing and auth pages (`layouts/auth`) load `resources/css/app.css` + `resources/js/app.js`.
- All npm/Vite commands run in the `node` Docker service (CLAUDE.md §9); never on the host.
- Menu pages load `menu-base.css`, the active theme stylesheet and `menu.js` (Alpine only). Never load marketing assets on menu pages.

## Filament panels
- Use Filament's native design system and components. No custom panel themes or Vite builds for panels unless approved; no Filament plugins without approval.
- Panel code lives in `app/Filament/Admin/` and `app/Filament/App/`; each panel discovers only its own directory, so navigation stays independent.
- Panel labels and titles use `__()`; tenant content is read through models and `HasTranslations`.
- Hiding navigation is never authorization: every page and resource must also be protected by `canAccess()` / policies.

## Menu themes
- All QR menu themes use the same underlying data structure: `MenuSnapshot`, `LiveMenuState`, `ThemeSettings`.
- Theme views live in `resources/views/themes/{key}/` and must not reference models, services, `DB`, `Cache`, `auth()` or `request()` (enforced by an architecture test).
- Theme views must render without executing any database query.
- Compose shared components from `resources/views/components/menu/`; override a component by placing a variant in `themes/{key}/components/`.
- Tenant customisation (colors, fonts) is applied through CSS custom properties, not per-tenant CSS builds.
- Keep theme-specific styling separate from business logic.
- Tenant-supplied text is rendered with `{{ }}` only.
