<?php

use App\Http\Controllers\Marketing\ContactController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\PricingController;
use App\Http\Controllers\Marketing\SitemapController;
use App\Http\Controllers\Marketing\StandsController;
use App\Http\Controllers\Marketing\ThemeShowcaseController;
use App\Http\Middleware\PreventSharedCaching;
use App\Http\Middleware\SetMarketingLocale;
use App\Support\MarketingUrl;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central (SaaS marketing) routes
|--------------------------------------------------------------------------
|
| Registered for the central domain only. Tenant subdomains have no route
| here, so e.g. {slug}.menitra.com/ returns 404 until public menus exist.
|
| One route group per platform locale: the default locale (tr) at /, the
| others under /{locale} (e.g. /en). Names: marketing.{locale}.{page}.
| Page names are locale-independent; page paths are localised below
| (e.g. features: /ozellikler ↔ /en/features), so MarketingUrl and the
| hreflang alternates work unchanged.
|
*/

$marketingPaths = [
    'features' => ['tr' => 'ozellikler', 'en' => 'features'],
    'pricing' => ['tr' => 'fiyatlar', 'en' => 'pricing'],
    'stands' => ['tr' => 'qr-stand', 'en' => 'qr-stands'],
    'contact' => ['tr' => 'iletisim', 'en' => 'contact'],
    'themes' => ['tr' => 'temalar', 'en' => 'themes'],
    'privacy' => ['tr' => 'gizlilik', 'en' => 'privacy'],
    'terms' => ['tr' => 'kullanim-kosullari', 'en' => 'terms'],
];

Route::domain(config('tenancy.central_domain'))->group(function () use ($marketingPaths): void {
    Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

    foreach (config('locales.interface') as $locale) {
        Route::prefix(MarketingUrl::prefix($locale))
            ->middleware([SetMarketingLocale::class.':'.$locale, PreventSharedCaching::class])
            ->name("marketing.{$locale}.")
            ->group(function () use ($locale, $marketingPaths): void {
                Route::get('/', HomeController::class)->name('home');
                Route::view($marketingPaths['features'][$locale] ?? 'features', 'marketing.features')->name('features');
                Route::get($marketingPaths['pricing'][$locale] ?? 'pricing', PricingController::class)->name('pricing');
                Route::get($marketingPaths['stands'][$locale] ?? 'qr-stands', StandsController::class)->name('stands');
                Route::get($marketingPaths['themes'][$locale] ?? 'themes', ThemeShowcaseController::class)->name('themes');
                Route::get($marketingPaths['contact'][$locale] ?? 'contact', [ContactController::class, 'show'])->name('contact');
                Route::post($marketingPaths['contact'][$locale] ?? 'contact', [ContactController::class, 'store'])
                    ->middleware('throttle:contact')
                    ->name('contact.store');
                // Legal drafts (lang/{locale}/legal.php; legal review pending).
                Route::view($marketingPaths['privacy'][$locale] ?? 'privacy', 'marketing.legal', ['document' => 'privacy'])->name('privacy');
                Route::view($marketingPaths['terms'][$locale] ?? 'terms', 'marketing.legal', ['document' => 'terms'])->name('terms');
            });
    }
});

require __DIR__.'/auth.php';
