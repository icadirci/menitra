<?php

use App\Http\Controllers\Marketing\HomeController;
use App\Http\Middleware\SetMarketingLocale;
use App\Support\MarketingUrl;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central (SaaS marketing) routes
|--------------------------------------------------------------------------
|
| Registered for the central domain only. Tenant subdomains have no route
| here, so e.g. {slug}.restano.app/ returns 404 until public menus exist.
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
];

Route::domain(config('tenancy.central_domain'))->group(function () use ($marketingPaths): void {
    foreach (array_keys(config('locales.supported')) as $locale) {
        Route::prefix(MarketingUrl::prefix($locale))
            ->middleware(SetMarketingLocale::class.':'.$locale)
            ->name("marketing.{$locale}.")
            ->group(function () use ($locale, $marketingPaths): void {
                Route::get('/', HomeController::class)->name('home');
                Route::view($marketingPaths['features'][$locale] ?? 'features', 'marketing.features')->name('features');
            });
    }
});

require __DIR__.'/auth.php';
