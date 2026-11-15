<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marketing pages depend on the session (the nav shows "My account" to
 * signed-in users, forms carry a CSRF token), so shared caches (CDN, proxies)
 * must never store them: `private, no-cache` replaces any public directive.
 */
final class PreventSharedCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->removeCacheControlDirective('public');
        $response->headers->removeCacheControlDirective('s-maxage');
        $response->headers->addCacheControlDirective('private');
        $response->headers->addCacheControlDirective('no-cache');

        return $response;
    }
}
