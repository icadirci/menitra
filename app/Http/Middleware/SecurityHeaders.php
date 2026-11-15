<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for every response (global middleware): CSP, nosniff,
 * frame denial, referrer and permissions policies, HSTS on HTTPS.
 *
 * Two CSP profiles (docs/architecture/tenancy.md § Security headers):
 * - public (marketing, auth, /account, QR menus): no inline or third-party
 *   scripts. 'unsafe-eval' is required by Alpine's standard build (its CSP
 *   build would be a new dependency); 'unsafe-inline' styles are needed for
 *   the tenant CSS custom properties on the menu root and inline style
 *   attributes in the marketing views.
 * - panels (/admin, /app, Livewire endpoints): Filament and Livewire inject
 *   inline scripts, so scripts also allow 'unsafe-inline' (documented
 *   exception); everything else matches the public profile.
 * Headers a response already carries are never overwritten.
 */
final class SecurityHeaders
{
    public function __construct(private readonly Vite $vite) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => $this->policy($this->isPanel($request)),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ];

        if ($request->isSecure()) {
            // No includeSubDomains: tenant subdomains are not opted in from here.
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    private function isPanel(Request $request): bool
    {
        $name = (string) $request->route()?->getName();

        return str_starts_with($name, 'filament.')
            || str_starts_with($name, 'livewire.')
            || $request->is('admin', 'admin/*', 'app', 'app/*', 'livewire*');
    }

    private function policy(bool $panel): string
    {
        $hot = $this->hotOrigins();
        $media = $this->origin((string) config('filesystems.disks.media.url'));

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", ...($panel ? ["'unsafe-inline'"] : []), "'unsafe-eval'", ...$hot['http']],
            'style-src' => ["'self'", "'unsafe-inline'", ...$hot['http']],
            'img-src' => ["'self'", 'data:', ...($panel ? ['blob:'] : []), ...array_filter([$media])],
            'font-src' => ["'self'", 'data:', ...$hot['http']],
            'connect-src' => ["'self'", ...$hot['http'], ...$hot['ws']],
            'worker-src' => ["'self'", ...($panel ? ['blob:'] : [])],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        return implode('; ', array_map(
            fn (string $directive, array $sources): string => $directive.' '.implode(' ', array_unique($sources)),
            array_keys($directives),
            $directives,
        ));
    }

    /**
     * The Vite dev server (public/hot) in development only.
     *
     * @return array{http: list<string>, ws: list<string>}
     */
    private function hotOrigins(): array
    {
        if (! $this->vite->isRunningHot()) {
            return ['http' => [], 'ws' => []];
        }

        $origin = $this->origin(trim((string) @file_get_contents($this->vite->hotFile())));

        return $origin === null
            ? ['http' => [], 'ws' => []]
            : ['http' => [$origin], 'ws' => [preg_replace('#^http#', 'ws', $origin)]];
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
