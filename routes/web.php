<?php

use App\Http\Controllers\Marketing\ContactController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\PricingController;
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
    'pricing' => ['tr' => 'fiyatlar', 'en' => 'pricing'],
    'contact' => ['tr' => 'iletisim', 'en' => 'contact'],
];

Route::domain(config('tenancy.central_domain'))->group(function () use ($marketingPaths): void {
    foreach (array_keys(config('locales.supported')) as $locale) {
        Route::prefix(MarketingUrl::prefix($locale))
            ->middleware(SetMarketingLocale::class.':'.$locale)
            ->name("marketing.{$locale}.")
            ->group(function () use ($locale, $marketingPaths): void {
                Route::get('/', HomeController::class)->name('home');
                Route::view($marketingPaths['features'][$locale] ?? 'features', 'marketing.features')->name('features');
                Route::get($marketingPaths['pricing'][$locale] ?? 'pricing', PricingController::class)->name('pricing');
                Route::get($marketingPaths['contact'][$locale] ?? 'contact', [ContactController::class, 'show'])->name('contact');
                Route::post($marketingPaths['contact'][$locale] ?? 'contact', [ContactController::class, 'store'])
                    ->middleware('throttle:contact')
                    ->name('contact.store');
            });
    }
});

require __DIR__.'/auth.php';
