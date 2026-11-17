<?php

declare(strict_types=1);

namespace App\Filament\Admin\Forms;

use Closure;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;

/**
 * Translatable fields for platform-level records (plans, marketing content):
 * one tab per interface locale (config('locales.interface'), default first),
 * state path `translations.{locale}.{field}`. Save the state through
 * TranslationWriter::syncPlatform(), which re-validates it on the server.
 */
final class PlatformTranslatableFields
{
    /**
     * @param  Closure(string $locale, bool $isDefault): array<int, mixed>  $fields
     */
    public static function make(Closure $fields, string $statePath = 'translations'): Tabs
    {
        $default = config('locales.default');

        $tabs = array_map(
            fn (string $locale): Tab => Tab::make(config("locales.supported.{$locale}", $locale))
                ->badge($locale === $default ? __('panels.app.translations.default') : null)
                ->statePath($locale)
                ->schema($fields($locale, $locale === $default)),
            self::locales(),
        );

        return Tabs::make(__('panels.app.translations.label'))
            ->statePath($statePath)
            ->tabs($tabs)
            ->columnSpanFull();
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, array<string, mixed>>
     */
    public static function fillFrom(Model $record, array $fields): array
    {
        $translations = $record->translations()->get()->keyBy('locale');
        $state = [];

        foreach (self::locales() as $locale) {
            $state[$locale] = array_merge(
                array_fill_keys($fields, null),
                $translations->get($locale)?->only($fields) ?? [],
            );
        }

        return $state;
    }

    /**
     * @return list<string> default locale first
     */
    private static function locales(): array
    {
        $default = config('locales.default');
        $locales = config('locales.interface');

        return array_values(array_unique([$default, ...$locales]));
    }
}
