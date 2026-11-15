<?php

use App\Http\Controllers\PublicMenu\LocationController;
use App\Http\Controllers\PublicMenu\MenuController;
use App\Http\Controllers\PublicMenu\QrRedirectController;
use App\Http\Controllers\PublicMenu\TenantHomeController;
use App\Http\Middleware\ResolveMenuLocale;
use App\Http\Middleware\ResolveTenantFromRequest;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public QR menu routes
|--------------------------------------------------------------------------
|
| Registered WITHOUT the `web` group: no session, no cookies, no CSRF, so
| pages can be cached publicly (docs/architecture/caching.md § HTTP caching).
| Subdomain ({slug}.central) and path (central/m/{slug}) forms share the
| same controllers; URLs are built only by MenuUrlGenerator.
|
*/

$slug = '[a-z0-9](?:-?[a-z0-9])*';
$segment = '[a-z0-9](?:-?[a-z0-9])*';
$central = config('tenancy.central_domain');

Route::domain($central)->group(function () use ($slug, $segment): void {
    Route::get('/q/{code}', QrRedirectController::class)
        ->where('code', '[0-9A-Za-z]{10}')
        ->middleware('throttle:qr')
        ->name('menu.qr');

    Route::prefix('m/{tenant}')
        ->where(['tenant' => $slug, 'location' => $segment, 'menu' => $segment])
        ->middleware(['throttle:menu', ResolveTenantFromRequest::class, ResolveMenuLocale::class])
        ->name('menu.path.')
        ->group(function (): void {
            Route::get('/', TenantHomeController::class)->name('home');
            Route::get('/{location}', LocationController::class)->name('location');
            Route::get('/{location}/{menu}', MenuController::class)->name('menu');
        });
});

Route::domain('{tenant}.'.$central)
    ->where(['tenant' => $slug, 'location' => $segment, 'menu' => $segment])
    ->middleware(['throttle:menu', ResolveTenantFromRequest::class, ResolveMenuLocale::class])
    ->name('menu.subdomain.')
    ->group(function (): void {
        Route::get('/', TenantHomeController::class)->name('home');
        Route::get('/{location}', LocationController::class)->name('location');
        Route::get('/{location}/{menu}', MenuController::class)->name('menu');
    });
