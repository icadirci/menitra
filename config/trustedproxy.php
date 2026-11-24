<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
|
| Read by Laravel's global TrustProxies middleware. In production a TLS
| reverse proxy on the host (nginx) forwards to the Compose webserver, which
| is bound to 127.0.0.1; PHP then sees the Docker bridge gateway as the
| client. Only requests from these addresses may set X-Forwarded-For / -Proto
| / -Port (X-Forwarded-Host is never trusted, see bootstrap/app.php).
|
| Comma-separated IPs or CIDR ranges, e.g. "172.16.0.0/12". Empty: no proxy
| is trusted (local development). Never use "*" while the webserver port is
| reachable from outside the host.
|
*/

return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
