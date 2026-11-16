<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Http\Controllers\Controller;
use App\Services\Admin\Impersonation;
use Illuminate\Http\RedirectResponse;

/**
 * POST /impersonation/leave: ends the impersonation and returns to the
 * impersonated user's page in /admin (or to the login when the admin can't be
 * restored).
 */
class LeaveImpersonationController extends Controller
{
    public function __invoke(Impersonation $impersonation): RedirectResponse
    {
        $userId = $impersonation->state()['user_id'] ?? null;

        abort_if($userId === null, 404);

        if ($impersonation->leave() === null) {
            return redirect()->route('login');
        }

        return redirect()->to(UserResource::getUrl('view', ['record' => $userId], panel: 'admin'));
    }
}
