# Database Architecture

> Status: **Approved — v0.4** (2026-11-15: `tenant_locales` without `is_default`, `location_item_states.sold_out_until`, `PlanFeature::max_qr_codes`). v0.3: platform roles, `TenantRole.branch_staff`, `location_user`.
>
> **Implemented so far:** `users.platform_role`, `tenants`, `tenant_translations`, `tenant_locales`, `tenant_user`, `locations`, `location_translations`, `location_opening_hours`, `location_special_hours`, `location_user`, `location_item_states`, `location_menu_hours`, `menu_categories` (+ translations), `menu_items` (+ translations), `menu_item_allergens`, `menu_item_dietary_labels`, `menu_item_variants` (+ translations), `extra_groups` (+ translations), `extras` (+ translations), `menu_item_extra_group`, `qr_codes`, `menus`, `menu_translations`, `location_menu`, `plans` (+ `is_featured`), `plan_translations`, `plan_prices`, `plan_limits`, `subscriptions` (+ `plan_price_id`), `subscription_events`, `faqs`, `faq_translations`, `testimonials`, `testimonial_translations`, `site_settings`, `site_setting_translations`. The rest of this document is the approved target schema. PostgreSQL. Every change goes through a migration; this document must be updated with it.

## Contents

- [Conventions](#conventions)
- [Entity relationship diagram](#entity-relationship-diagram)
- [Schema](#schema)
  - [Platform and tenancy](#platform-and-tenancy)
  - [Locations](#locations)
  - [Menus and content](#menus-and-content)
  - [QR codes](#qr-codes)
  - [Subscriptions](#subscriptions)
- [Tenant integrity constraints](#tenant-integrity-constraints)
- [Translations](#translations)
- [Enums](#enums)
- [Deletion and retention](#deletion-and-retention)

## Conventions

| Topic | Rule |
|---|---|
| Primary keys | `id bigint`. Entities referenced publicly get a `public_id` ULID |
| Tenant-owned tables (🔒 below) | `tenant_id bigint NOT NULL`, `UNIQUE (tenant_id, id)`, `BelongsToTenant` model trait |
| Timestamps | All tables have `created_at` / `updated_at` |
| Money | `bigint` in minor units (kuruş); currency on the tenant or on the price row (`TRY` default) |
| Fixed sets | PHP enums stored as `varchar` |
| Translatable text | Only in `{entity}_translations` tables |
| Ordering | `sort_order integer NOT NULL DEFAULT 0` |
| JSON | `jsonb` only for genuinely unstructured data (`theme_settings.settings`, `subscription_events.meta`) |
| Soft deletes | `tenants`, `locations`, `menus` |
| Indexes | Composite indexes lead with `tenant_id` where queries are tenant-scoped |

## Entity relationship diagram

```
                                   ┌──────────────┐
                                   │    plans     │──1:*── plan_prices (interval, amount, currency)
                                   └──────┬───────┘──1:*── plan_limits (feature, limit_value)
                                          │1            ──1:*── plan_translations
                                          │*
 users ─*:*─ tenant_user ─*:1─ ┌──────────┴───────┐ 1:* subscription_events
   (platform_role) (role)      │     tenants      │──1:*── subscriptions
                               │  (one business)  │──1:*── tenant_locales (tr, en …)
                               └──┬─────┬─────┬───┘──1:*── tenant_translations
                                  │1    │1    │1   ──1:*── theme_settings (per theme)
                                  │     │     │    ──1:*── tenant_domains (later phase)
             ┌────────────────────┘     │     └──────────────────┐
             │*                         │*                       │*
      ┌──────┴──────┐  *:*        ┌─────┴─────┐            ┌─────┴───────┐
      │  locations  │──────────── │   menus   │            │ extra_groups│──1:*── extras
      └──┬───┬───┬──┘ location_menu└─────┬─────┘            └─────┬───────┘
         │   │   │   (sort, active)     │1                        │*
         │   │   │        │1            │*                        │
         │   │   │   location_menu_hours│                         │
         │   │   │                ┌─────┴──────────┐              │
         │   │   │                │menu_categories │              │
         │   │   │                └─────┬──────────┘              │
         │   │   │                      │1                        │
         │   │   │                      │*                        │
         │   │   │                ┌─────┴──────┐  *:* menu_item_extra_group
         │   │   │                │ menu_items │───────────────────┘
         │   │   │                └─┬──┬──┬────┘
         │   │   │                  │  │  └──1:*── menu_item_variants
         │   │   │                  │  └─────1:*── menu_item_allergens       (enum value)
         │   │   │                  └────────1:*── menu_item_dietary_labels  (enum value)
         │   │   └──1:*── location_opening_hours / location_special_hours
         │   └──────1:*── location_item_states (sold-out per branch) ──*:1── menu_items
         ├──────────1:*── qr_codes ──*:0..1── menus
         └──────────*:*── tenant_user via location_user (branch assignments)

 Every customer-facing entity also has a *_translations table (1:* by locale).
```

## Schema

### Platform and tenancy

| Table | Columns | Constraints / indexes |
|---|---|---|
| `tenants` | `public_id` ulid, `slug`, `status` (`TenantStatus`), `currency` char(3) default `TRY`, `default_locale` varchar(10), `theme` (`MenuTheme`), `logo_path`, `cover_path`, `deleted_at` | unique `slug`; unique `public_id`. **`default_locale` is the single source of truth for the tenant's default language**; it is always one of the tenant's `tenant_locales` (enforced by `TenantLocaleService`, covered by tests) |
| `tenant_translations` | `tenant_id` FK cascade, `locale`, `name`, `tagline` nullable, `description` nullable text | unique (`tenant_id`, `locale`) |
| `tenant_locales` 🔒 | `locale`, `sort_order` | unique (`tenant_id`, `locale`); unique (`tenant_id`, `id`). The set of enabled locales and their order. **No `is_default` column** (decision 2026-11-15): the default is `tenants.default_locale`, so the two can never disagree. Disabling a locale never deletes translations. The creating migration backfilled one row per existing tenant from its `default_locale` |
| `tenant_domains` 🔒 | `domain` (lowercase), `is_primary`, `verified_at` nullable | unique `domain`. Used in a later phase |
| `users` | Laravel default (`name`, `email`, `password`, `email_verified_at`, `remember_token`), `platform_role` nullable (`PlatformRole`) | unique `email`. `platform_role` is null for business users and never mass-assignable |
| `tenant_user` (membership) | `id`, `tenant_id` FK cascade, `user_id` FK cascade, `role` (`TenantRole`), `is_active` boolean default true | unique (`tenant_id`, `user_id`); **unique (`tenant_id`, `id`)** (target of `location_user`); index `user_id`. Not globally tenant-scoped: it is always read from the user side (`$user->memberships()`) |
| `location_user` (branch assignment) | `id`, `tenant_id`, `tenant_user_id`, `location_id` | unique (`tenant_user_id`, `location_id`); composite FKs to `tenant_user (tenant_id, id)` and `locations (tenant_id, id)`, both cascade. Rows are needed only for `manager` and `branch_staff`; owners reach every branch implicitly |
| `theme_settings` 🔒 | `theme` (`MenuTheme`), `settings` jsonb (`colors`, `font_pair`, `layout`; validated by `MenuTheme::validator()`, see [themes.md](themes.md#styling-and-customisation)) | unique (`tenant_id`, `theme`). No row = the theme's defaults; registration writes the `classic` row. Created in B9 (no backfill) |

### Locations

| Table | Columns | Constraints / indexes |
|---|---|---|
| `locations` 🔒 | `slug`, `is_active`, `is_default`, `theme` nullable (override), `phone`, `email`, `whatsapp`, `address_line`, `district`, `city`, `postal_code`, `country_code` char(2) default `TR`, `latitude` / `longitude` numeric(9,6) nullable, `timezone` default `Europe/Istanbul`, `cover_path`, `sort_order`, `deleted_at` | unique (`tenant_id`, `slug`); partial unique (`tenant_id`) where `is_default` |
| `location_translations` | `location_id` FK cascade, `locale`, `name`, `description` nullable | unique (`location_id`, `locale`) |
| `location_opening_hours` 🔒 | `location_id`, `day_of_week` smallint (1–7, ISO), `opens_at` time, `closes_at` time | index (`location_id`, `day_of_week`); checks `day_of_week BETWEEN 1 AND 7`, `opens_at <> closes_at`. Several rows per day allowed (split shifts); `closes_at < opens_at` means open past midnight. Overlapping intervals of the same day are rejected by `LocationHoursService` |
| `location_special_hours` 🔒 | `location_id`, `date`, `is_closed`, `opens_at` / `closes_at` nullable | unique (`location_id`, `date`, `opens_at`); partial unique (`location_id`, `date`) where `is_closed`; check: closed rows have no times, open rows have both (and they differ). A date is either closed or has non-overlapping intervals (`LocationHoursService`). The intervals of a date replace that weekday's hours. Covers holidays such as Bayram |
| `location_item_states` 🔒 | `location_id`, `menu_item_id`, `is_sold_out` boolean, `sold_out_until` timestamp nullable (UTC, like every timestamp: the app timezone is UTC) | unique (`location_id`, `menu_item_id`). A missing row means the item default applies. Semantics: `is_sold_out = false` → on sale; `is_sold_out = true` and `sold_out_until IS NULL` → sold out until someone turns it back on; `is_sold_out = true` and `sold_out_until` in the future → sold out until then, on sale again automatically afterwards. Comparisons are in UTC at read time; **no scheduler** resets rows, and an expired row is simply treated as on sale |

### Menus and content

| Table | Columns | Constraints / indexes |
|---|---|---|
| `menus` 🔒 | `slug`, `type` (`MenuType`), `is_published`, `sort_order`, `deleted_at` | unique (`tenant_id`, `slug`) |
| `menu_translations` | `menu_id`, `locale`, `name`, `description` nullable | unique (`menu_id`, `locale`) |
| `location_menu` 🔒 | `location_id`, `menu_id`, `is_active`, `sort_order` | unique (`tenant_id`, `location_id`, `menu_id`) |
| `location_menu_hours` 🔒 | `location_menu_id`, `day_of_week`, `starts_at`, `ends_at` | Optional. No rows means served all day. Only used for "now serving" |
| `menu_categories` 🔒 | `menu_id`, `image_path`, `is_visible`, `sort_order` | index (`tenant_id`, `menu_id`, `sort_order`) |
| `menu_category_translations` | `menu_category_id`, `locale`, `name`, `description` nullable | unique (`menu_category_id`, `locale`) |
| `menu_items` 🔒 | `public_id` ulid, `menu_category_id`, `price` bigint nullable, `image_path`, `is_visible`, `is_available`, `is_featured`, `is_popular`, `calories` nullable, `preparation_minutes` nullable, `sort_order` | unique `public_id`; index (`tenant_id`, `menu_category_id`, `sort_order`); partial index (`tenant_id`) where `is_featured` |
| `menu_item_translations` | `menu_item_id`, `locale`, `name`, `description` nullable | unique (`menu_item_id`, `locale`) |
| `menu_item_variants` 🔒 | `public_id` ulid, `menu_item_id`, `price` bigint, `is_default`, `is_available`, `sort_order` | index (`menu_item_id`, `sort_order`); partial unique (`menu_item_id`) where `is_default`; check `price >= 0` |
| `menu_item_variant_translations` | `menu_item_variant_id`, `locale`, `name` | unique (…, `locale`) |
| `extra_groups` 🔒 | `selection_type` (`ExtraSelectionType`), `min_select` default 0, `max_select` nullable, `is_required`, `sort_order` | Reusable across items; check `max_select IS NULL OR max_select >= min_select` |
| `extra_group_translations` | `extra_group_id`, `locale`, `name` | unique (…, `locale`) |
| `extras` 🔒 | `public_id` ulid, `extra_group_id`, `price_delta` bigint default 0, `is_available`, `sort_order` | index (`extra_group_id`, `sort_order`) |
| `extra_translations` | `extra_id`, `locale`, `name` | unique (…, `locale`) |
| `menu_item_extra_group` 🔒 | `menu_item_id`, `extra_group_id`, `sort_order` | unique (`menu_item_id`, `extra_group_id`) |
| `menu_item_allergens` 🔒 | `menu_item_id`, `allergen` (`Allergen`) | unique (`menu_item_id`, `allergen`) |
| `menu_item_dietary_labels` 🔒 | `menu_item_id`, `label` (`DietaryLabel`) | unique (`menu_item_id`, `label`) |

### QR codes

| Table | Columns | Constraints / indexes |
|---|---|---|
| `qr_codes` 🔒 | `code` (10-character random Crockford base32), `location_id`, `menu_id` nullable (deep link), `label`, `table_label` nullable (reserved for future table service), `is_active`, `last_scanned_at` nullable (set by `RecordQrCodeScan`, at most hourly) | unique `code` (global) |
| `qr_scans` | **Not created in v1.** Added with analytics, fed by the `QrCodeScanned` event | |

### Subscriptions

| Table | Columns | Constraints / indexes |
|---|---|---|
| `plans` | `code`, `is_active`, `is_public`, `is_featured` ("most popular", at most one — enforced by `PlanService`), `trial_days`, `sort_order` | unique `code` |
| `plan_translations` | `plan_id`, `locale`, `name`, `description` | unique (`plan_id`, `locale`) |
| `plan_prices` | `plan_id` FK cascade, `interval` (`BillingInterval`: `monthly`, `yearly`), `amount` bigint (kuruş), `currency` char(3) default `TRY`, `is_active` | unique (`plan_id`, `interval`, `currency`); check `amount >= 0`. Rows are deactivated, never deleted, when a price is removed |
| `plan_limits` | `plan_id` FK cascade, `feature` (`PlanFeature`), `limit_value` int nullable (null = unlimited; 0/1 for boolean features) | unique (`plan_id`, `feature`); check `limit_value IS NULL OR limit_value >= 0`. A missing row means the feature isn't included |
| `subscriptions` 🔒 | `plan_id`, `plan_price_id` nullable FK restrict (null during trials), `status` (`SubscriptionStatus`), `trial_ends_at`, `current_period_starts_at`, `current_period_ends_at`, `cancel_at_period_end`, `canceled_at`, `grace_ends_at`, `ended_at`, `provider` nullable, `provider_reference` nullable | partial unique (`tenant_id`) where `ended_at IS NULL`; index (`status`, `current_period_ends_at`) |
| `subscription_events` 🔒 | `subscription_id`, `type` (`SubscriptionEventType`), `from_status`, `to_status`, `meta` jsonb, `occurred_at` | Append-only; index (`subscription_id`, `occurred_at`) |

Subscription semantics are in [subscriptions.md](subscriptions.md).

### Marketing content

Platform-level (no `tenant_id`), managed in `/admin`, read only by `App\Services\Marketing\MarketingContent`.

| Table | Columns | Constraints / indexes |
|---|---|---|
| `faqs` | `placement` (`FaqPlacement`: `home`, `pricing`), `is_published`, `sort_order` | index (`placement`, `is_published`, `sort_order`) |
| `faq_translations` | `faq_id` FK cascade, `locale`, `question`, `answer` text | unique (`faq_id`, `locale`) |
| `testimonials` | `author_name` (not translated), `is_published`, `sort_order` | index (`is_published`, `sort_order`) |
| `testimonial_translations` | `testimonial_id` FK cascade, `locale`, `quote` text, `author_role` nullable | unique (`testimonial_id`, `locale`) |
| `site_settings` | Platform contact details: `contact_email`, `phone`, `phone_has_whatsapp`, `{instagram,facebook,x,linkedin,youtube,tiktok}_url` (one column per `SocialNetwork` case), all nullable | Single row: `CHECK (id = 1)`; `SiteSetting::current()` creates it on first save in `/admin`, public pages only read (`existing()`) |
| `site_setting_translations` | `site_setting_id` FK cascade, `locale`, `address` nullable, `support_hours` nullable | unique (`site_setting_id`, `locale`); no required field; read with per-field fallback to the platform default locale |

## Tenant integrity constraints

The global scope stops cross-tenant **reads**. The database stops cross-tenant **writes**: for example, attaching tenant B's menu to tenant A's location, or putting tenant B's item into tenant A's category.

Pattern:

```sql
-- parent
ALTER TABLE menus ADD CONSTRAINT menus_tenant_id_id_unique UNIQUE (tenant_id, id);

-- child
FOREIGN KEY (tenant_id, menu_id) REFERENCES menus (tenant_id, id) ON DELETE CASCADE
```

In Laravel migrations:

```php
$table->foreign(['tenant_id', 'menu_id'])->references(['tenant_id', 'id'])->on('menus')->cascadeOnDelete();
```

Composite foreign keys:

| Child | References |
|---|---|
| `tenant_locales`, `locations`, `menus`, `extra_groups`, `theme_settings`, `qr_codes`, `subscriptions`, `tenant_domains` | `tenants (id)` via `tenant_id` |
| `location_opening_hours`, `location_special_hours` | `locations (tenant_id, id)` |
| `location_menu` | `locations (tenant_id, id)`, `menus (tenant_id, id)` |
| `tenant_user` | `tenants (id)` via `tenant_id`; carries `UNIQUE (tenant_id, id)` |
| `location_user` | `tenant_user (tenant_id, id)`, `locations (tenant_id, id)`. A membership of tenant A can't be assigned a branch of tenant B |
| `location_menu_hours` | `location_menu (tenant_id, id)` |
| `location_item_states` | `locations (tenant_id, id)`, `menu_items (tenant_id, id)` |
| `menu_categories` | `menus (tenant_id, id)` |
| `menu_items` | `menu_categories (tenant_id, id)` |
| `menu_item_variants`, `menu_item_allergens`, `menu_item_dietary_labels` | `menu_items (tenant_id, id)` |
| `extras` | `extra_groups (tenant_id, id)` |
| `menu_item_extra_group` | `menu_items (tenant_id, id)`, `extra_groups (tenant_id, id)` |
| `qr_codes` | `locations (tenant_id, id)`; `menus (tenant_id, id)`, nullable, `MATCH SIMPLE` so it's skipped when null |
| `subscription_events` | `subscriptions (tenant_id, id)` |

**Translation tables have no `tenant_id`.** Each row references exactly one parent through a plain FK with `ON DELETE CASCADE`. There is no second reference that could disagree with the parent's tenant, so isolation is inherited from the parent.

**Rules a foreign key can't express** are enforced in services and covered by tests:
- A `qr_codes.menu_id` must be attached to its `location_id` through `location_menu`. At redirect time it falls back to the location page if not.
- A rendered item's category must belong to the menu being rendered.
- At most one default variant per item (also a partial unique index; `MenuContentService` clears the previous default first).
- Pivots that carry `tenant_id` and are written through relations use pivot models with `BelongsToTenant` (`LocationMenu`, `MenuItemExtraGroup`), which set `tenant_id` from the context; relations don't use `withPivotValue('tenant_id')`, so they stay eager-loadable.

## Translations

| Option considered | Decision |
|---|---|
| JSON column per field (e.g. `spatie/laravel-translatable`) | Rejected: goes against the relational-first rule, weak per-locale constraints, new dependency |
| One polymorphic `translations` table | Rejected: no real FKs, no NOT NULL guarantees |
| **One `{entity}_translations` table per entity** | **Chosen**: real FKs with cascade, unique per locale, typed columns, eager-loadable |

- Base tables contain **no** translatable text columns. Each piece of text has a single source of truth.
- Translatable entities: tenants, locations, menus, menu categories, menu items, variants, extra groups, extras, plans, marketing FAQ, testimonials, site settings. Platform-level ones (plans, FAQ, testimonials, site settings) use the platform locales (`config('locales.supported')`, default `config('locales.default')`) and are written by `TranslationWriter::syncPlatform()`.
- The in-house trait `App\Models\Concerns\HasTranslations` provides:
  - `translations()` (hasMany)
  - `translate(?string $locale)`, with fallback order: requested locale → tenant default locale → first available
  - `scopeWithTranslations(array $locales)` to eager load only the needed locales
- **Platform locales** are listed in `config/locales.php` (initially `tr`, `en`, with native names).
- **Tenant locales** are stored in `tenant_locales` (enabled set + order); the default is `tenants.default_locale`. `locale` values are validated against the config in `TenantLocaleService` (and in Form Requests where a request carries them). `Tenant::enabledLocales()` returns the default first, then by `sort_order`.
- Translation writes from the panel go through `App\Services\Translations\TranslationWriter`: only enabled locales are accepted, the default locale's required fields must be filled, and rows of locales that aren't enabled are never touched.
- **Adding a language:** add a config entry, add `lang/{xx}/*.php` files, and let tenants enable it. No schema change is needed.
- UI strings (labels, allergen and dietary names, the unavailable page) live in `lang/{locale}/`. They are never hard-coded.

## Enums

| Enum | Values |
|---|---|
| `TenantStatus` | `active`, `suspended` |
| `PlatformRole` | `super_admin`, `platform_staff` (on `users.platform_role`, nullable) |
| `TenantRole` | `owner`, `manager`, `branch_staff` |
| `MenuType` | `breakfast`, `lunch`, `dinner`, `beverages`, `desserts`, `all_day`, `other` |
| `MenuTheme` | `classic`, `minimal`, `bistro`, `cafe`, `grid` |
| `ExtraSelectionType` | `single`, `multiple` |
| `Allergen` | The 14 EU / Turkish Food Codex allergens: `gluten`, `crustaceans`, `eggs`, `fish`, `peanuts`, `soybeans`, `milk`, `nuts`, `celery`, `mustard`, `sesame`, `sulphites`, `lupin`, `molluscs` |
| `DietaryLabel` | `vegan`, `vegetarian`, `gluten_free`, `lactose_free`, `halal`, `spicy`, `organic`, `sugar_free` (extendable) |
| `SubscriptionStatus` | `trialing`, `active`, `past_due`, `grace`, `expired` |
| `SubscriptionEventType` | `trial_started`, `activated`, `renewed`, `payment_failed`, `cancel_requested`, `cancel_reverted`, `grace_started`, `expired`, `plan_changed`, `reactivated` |
| `BillingInterval` | `monthly`, `yearly` |
| `PlanFeature` | `max_locations`, `max_menus`, `max_items`, `max_languages`, `max_qr_codes`, `premium_themes`, `custom_domain`, `remove_branding`, `qr_analytics` |

Display names for enums come from `lang/{locale}/…`.

## Deletion and retention

- Tenants, locations and menus use soft deletes.
- Deleting a menu cascades to its categories and items only when it's force-deleted.
- **No tenant data is ever deleted automatically.** Expired subscriptions keep all data. A purge may only run from an explicit, documented retention policy, which hasn't been defined yet.
- Destructive schema changes require explicit approval.
