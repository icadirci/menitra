<?php

declare(strict_types=1);

namespace App\Filament\App\Forms;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;

/**
 * Translatable form fields without a translation package: one tab per enabled
 * tenant locale (default first), state path `translations.{locale}.{field}`.
 *
 *     TranslatableFields::make(fn (string $locale, bool $isDefault): array => [
 *         TextInput::make('name')->required($isDefault)->maxLength(255),
 *         Textarea::make('description'),
 *     ])
 *
 * Fill with fillFrom($record); save the `translations` state through
 * App\Services\Translations\TranslationWriter (never ->relationship()), which
 * re-validates everything on the server.
 */
final class TranslatableFields
{
    /**
     * @param  Closure(string $locale, bool $isDefault): array<int, mixed>  $fields
     */
    public static function make(Closure $fields, string $statePath = 'translations'): Tabs
    {
        $tenant = self::tenant();

        $tabs = array_map(
            fn (string $locale): Tab => Tab::make(config("locales.supported.{$locale}", $locale))
                ->badge($locale === $tenant->default_locale ? __('panels.app.translations.default') : null)
                ->statePath($locale)
                ->schema($fields($locale, $locale === $tenant->default_locale)),
            $tenant->enabledLocales(),
        );

        return Tabs::make(__('panels.app.translations.label'))
            ->statePath($statePath)
            ->tabs($tabs);
    }

    /**
     * Form state for a record: [locale => attributes] for every enabled locale.
     *
     * @param  list<string>  $fields
     * @return array<string, array<string, mixed>>
     */
    public static function fillFrom(Model $record, array $fields = ['name', 'description']): array
    {
        $translations = $record->translations()->get()->keyBy('locale');
        $state = [];

        foreach (self::tenant()->enabledLocales() as $locale) {
            $state[$locale] = array_fill_keys($fields, null);

            if ($translation = $translations->get($locale)) {
                $state[$locale] = array_merge($state[$locale], $translation->only($fields));
            }
        }

        return $state;
    }

    private static function tenant(): Tenant
    {
        return app(TenantContext::class)->tenant();
    }
}
