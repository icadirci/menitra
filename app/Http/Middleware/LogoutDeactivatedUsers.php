<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Admin\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A user deactivated by a super admin loses an open session on the next
 * request (web group and both panels). If the deactivated user is being
 * impersonated, the impersonation ends and the admin is restored instead.
 */
class LogoutDeactivatedUsers
{
    public function __construct(private readonly Impersonation $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isDeactivated()) {
            return $next($request);
        }

        if ($this->impersonation->isActive() && $this->impersonation->leave('left') !== null) {
            return redirect()->to('/admin');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
