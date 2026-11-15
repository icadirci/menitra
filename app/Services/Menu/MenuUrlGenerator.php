<?php

declare(strict_types=1);

namespace App\Services\Menu;

/**
 * The only builder of public menu URLs. Canonical form in v1: the tenant's
 * subdomain ({slug}.{central domain}), with the scheme and port of APP_URL.
 * A verified custom domain will be chosen here later.
 */
final class MenuUrlGenerator
{
    public function tenant(string $tenantSlug): string
    {
        return $this->base($tenantSlug).'/';
    }

    public function location(string $tenantSlug, string $locationSlug): string
    {
        return $this->base($tenantSlug).'/'.rawurlencode($locationSlug);
    }

    public function menu(string $tenantSlug, string $locationSlug, string $menuSlug): string
    {
        return $this->location($tenantSlug, $locationSlug).'/'.rawurlencode($menuSlug);
    }

    /**
     * Path form on the central domain: /m/{slug}/…
     */
    public function path(string $tenantSlug, ?string $locationSlug = null, ?string $menuSlug = null): string
    {
        $url = $this->origin((string) config('tenancy.central_domain')).'/m/'.rawurlencode($tenantSlug);

        foreach (array_filter([$locationSlug, $menuSlug]) as $segment) {
            $url .= '/'.rawurlencode($segment);
        }

        return $url;
    }

    public function qr(string $code): string
    {
        return $this->origin((string) config('tenancy.central_domain')).'/q/'.rawurlencode($code);
    }

    private function base(string $tenantSlug): string
    {
        return $this->origin($tenantSlug.'.'.config('tenancy.central_domain'));
    }

    private function origin(string $host): string
    {
        $appUrl = parse_url((string) config('app.url'));
        $scheme = $appUrl['scheme'] ?? 'https';
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }
}
