<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the app locale for a marketing route group from its route definition
 * (tr at /, en at /en). The locale never comes from user input here.
 */
class SetMarketingLocale
{
    public function handle(Request $request, Closure $next, string $locale): Response
    {
        abort_unless(array_key_exists($locale, config('locales.supported')), 404);

        app()->setLocale($locale);

        return $next($request);
    }
}
