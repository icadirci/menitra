<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Context for the HTTP error pages (resources/views/errors).
 *
 * Menu hosts (tenant subdomains, /m/* and /q/* on the central domain) get a
 * plain platform page: no business details, no app.js, no session. Every
 * other central URL gets the marketing design. Error pages never touch the
 * database, so they also render when it is down.
 */
final class ErrorPage
{
    public static function isMenuHost(Request $request): bool
    {
        $central = strtolower((string) config('tenancy.central_domain'));

        return strtolower($request->getHost()) !== $central
            || $request->is('m', 'm/*', 'q', 'q/*');
    }

    /**
     * An explicit 403 reason (e.g. `abort(403, __('tenancy.suspended'))`) is
     * shown instead of the generic text; Laravel's untranslated default from
     * AuthorizationException is not.
     */
    public static function forbiddenReason(?Throwable $exception): ?string
    {
        if (! $exception instanceof HttpExceptionInterface || $exception->getStatusCode() !== 403) {
            return null;
        }

        $message = trim($exception->getMessage());
        $previous = $exception->getPrevious();

        if ($message === '' || ($previous instanceof AuthorizationException && $message === 'This action is unauthorized.')) {
            return null;
        }

        return $message;
    }

    /**
     * Unmatched URLs never run SetMarketingLocale: take the locale from the
     * marketing URL prefix (/en/...) so the page speaks the visitor's language.
     */
    public static function applyLocale(Request $request): void
    {
        if (self::isMenuHost($request)) {
            return;
        }

        $segment = (string) $request->segment(1);

        if ($segment !== '' && array_key_exists($segment, config('locales.supported'))) {
            app()->setLocale($segment);
        }
    }
}
