<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards Livewire's temporary file upload and preview endpoints. Livewire is
 * used only inside the Filament panels, so these endpoints are served only on
 * the central domain and only to users who may access at least one panel.
 * Livewire's own signed-URL check and throttling still apply after this.
 */
class RestrictLivewireFileEndpoints
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->getHost() === config('tenancy.central_domain'), 404);

        $user = $request->user();

        abort_unless($user instanceof User && $this->canAccessAnyPanel($user), 403);

        return $next($request);
    }

    private function canAccessAnyPanel(User $user): bool
    {
        foreach (Filament::getPanels() as $panel) {
            if ($user->canAccessPanel($panel)) {
                return true;
            }
        }

        return false;
    }
}
