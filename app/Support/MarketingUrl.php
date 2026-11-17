<?php

declare(strict_types=1);

namespace App\Support;

/**
 * URLs of the localised marketing pages. Routes are named
 * `marketing.{locale}.{page}`; the default locale has no URL prefix.
 */
final class MarketingUrl
{
    /**
     * Public marketing pages (GET), in sitemap order. Every page exists in
     * every interface locale (routes/web.php).
     *
     * @var list<string>
     */
    public const array PAGES = ['home', 'features', 'themes', 'pricing', 'contact', 'privacy', 'terms'];

    public static function to(string $page, ?string $locale = null, ?string $fragment = null): string
    {
        $locale ??= app()->getLocale();

        if (! in_array($locale, config('locales.interface'), true)) {
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

        foreach (config('locales.interface') as $locale) {
            $urls[$locale] = self::to($page, $locale);
        }

        return $urls;
    }

    public static function prefix(string $locale): string
    {
        return $locale === config('locales.default') ? '' : $locale;
    }
}
