<?php

use App\Exceptions\MenuUnavailableException;
use App\Http\Controllers\RobotsController;
use App\Http\Middleware\EnforceImpersonation;
use App\Http\Middleware\LogoutDeactivatedUsers;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration as Sentry;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Public QR menus: no `web` middleware group (no session or cookies).
        then: function (): void {
            Route::middleware([])->group(base_path('routes/menu.php'));
            // Payment provider callback and webhook: no session or CSRF (see the file).
            Route::middleware([])->group(base_path('routes/billing.php'));
            // Every host, no session; environment-aware (replaces public/robots.txt).
            Route::get('robots.txt', RobotsController::class)->name('robots');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Guests hitting `auth` routes go to the named `login` route (→ /app/login);
        // signed-in users hitting `guest` routes (register, password reset) go to /app.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo('/app');

        // Impersonation limits and deactivated accounts (launch plan Faz 8). The
        // panels list their own middleware and add both there too.
        $middleware->web(append: [EnforceImpersonation::class, LogoutDeactivatedUsers::class]);
        // Before authentication (after the session is started), so a deactivated
        // user is signed out instead of only refused by the panel's Authenticate.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnforceImpersonation::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, LogoutDeactivatedUsers::class);

        // CSP and other security headers on every response, menus included.
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Reported exceptions also go to Sentry (no-op without a DSN; see config/sentry.php).
        Sentry::handles($exceptions);

        // Expected state, not an error: every scan of an offline menu threw
        // into the log. It is still rendered below.
        $exceptions->dontReport(MenuUnavailableException::class);

        // Tenant hosts serve only GET menu routes: any other method is a 404
        // there (not 405), so central-only endpoints stay invisible.
        $exceptions->map(MethodNotAllowedHttpException::class, fn (MethodNotAllowedHttpException $exception) => strtolower(request()->getHost()) === strtolower((string) config('tenancy.central_domain'))
            ? $exception
            : new NotFoundHttpException(previous: $exception));

        // Suspended / expired tenants and inactive QR codes: platform page,
        // 503 without Retry-After, never cached, never indexed.
        $exceptions->render(fn (MenuUnavailableException $exception) => response()
            ->view('menu.unavailable', ['businessName' => $exception->businessName], 503)
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex'));
    })->create();
