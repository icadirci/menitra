<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Admin\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limits of an impersonated session (web group and both panels):
 *
 * - a stale state (the signed-in user isn't the impersonated one) is dropped;
 * - after Impersonation::MAX_MINUTES the admin is restored automatically;
 * - /admin is refused, and so are routes that change the account or create
 *   memberships on the user's behalf (team invitation acceptance).
 *
 * Password changes go through guest-only routes and two-factor changes are
 * refused by EditProfile and User itself.
 */
class EnforceImpersonation
{
    /** @var list<string> */
    private const array BLOCKED_ROUTES = [
        'invitations.accept',
    ];

    public function __construct(private readonly Impersonation $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $state = $this->impersonation->state();

        if ($state === null) {
            return $next($request);
        }

        if ((int) $request->user()?->getKey() !== $state['user_id']) {
            session()->forget(Impersonation::SESSION_KEY);

            return $next($request);
        }

        if ($this->impersonation->hasExpired()) {
            $admin = $this->impersonation->leave('expired');

            return redirect()->to($admin !== null ? '/admin' : '/app/login')
                ->with('impersonation_expired', true);
        }

        if ($request->is('admin', 'admin/*') || $request->routeIs(...self::BLOCKED_ROUTES)) {
            abort(403, __('admin.impersonation.blocked'));
        }

        return $next($request);
    }
}
