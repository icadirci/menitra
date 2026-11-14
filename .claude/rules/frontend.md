# Frontend Development Rules

## General
- Use Laravel Blade, Tailwind CSS and Alpine.js.
- Use Vite for asset compilation.
- Do not use React, Vue, Inertia, Livewire or Bootstrap.
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
- Marketing pages load `resources/css/app.css` + `resources/js/app.js`.
- Menu pages load `menu-base.css`, the active theme stylesheet and `menu.js` (Alpine only). Never load marketing assets on menu pages.

## Menu themes
- All QR menu themes use the same underlying data structure: `MenuSnapshot`, `LiveMenuState`, `ThemeSettings`.
- Theme views live in `resources/views/themes/{key}/` and must not reference models, services, `DB`, `Cache`, `auth()` or `request()` (enforced by an architecture test).
- Theme views must render without executing any database query.
- Compose shared components from `resources/views/components/menu/`; override a component by placing a variant in `themes/{key}/components/`.
- Tenant customisation (colors, fonts) is applied through CSS custom properties, not per-tenant CSS builds.
- Keep theme-specific styling separate from business logic.
- Tenant-supplied text is rendered with `{{ }}` only.
