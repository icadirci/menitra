<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ?lang=xx is used only when it is one of the tenant's enabled locales;
 * otherwise the tenant default applies. The locale lives in the URL, never in
 * a cookie, so shared caching stays correct.
 */
class ResolveMenuLocale
{
    public const string ATTRIBUTE = 'menuLocale';

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = ResolveTenantFromRequest::tenant($request);
        $requested = $request->query('lang');

        $locale = is_string($requested) && in_array($requested, $tenant->locales, true)
            ? $requested
            : $tenant->defaultLocale;

        app()->setLocale($locale);
        $request->attributes->set(self::ATTRIBUTE, $locale);

        return $next($request);
    }
}
