<?php

declare(strict_types=1);

namespace App\Support;

/**
 * URLs of the localised marketing pages. Routes are named
 * `marketing.{locale}.{page}`; the default locale has no URL prefix.
 */
final class MarketingUrl
{
    public static function to(string $page, ?string $locale = null, ?string $fragment = null): string
    {
        $locale ??= app()->getLocale();

        if (! array_key_exists($locale, config('locales.supported'))) {
            $locale = config('locales.default');
        }

        return route("marketing.{$locale}.{$page}").($fragment !== null ? '#'.$fragment : '');
    }

    /**
     * @return array<string, string> locale => URL of the same page
     */
    public static function alternates(string $page): array
    {
        $urls = [];

        foreach (array_keys(config('locales.supported')) as $locale) {
            $urls[$locale] = self::to($page, $locale);
        }

        return $urls;
    }

    public static function prefix(string $locale): string
    {
        return $locale === config('locales.default') ? '' : $locale;
    }
}
