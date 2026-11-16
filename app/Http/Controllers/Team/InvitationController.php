<?php

declare(strict_types=1);

namespace App\Http\Controllers\Team;

use App\Exceptions\InvitationEmailMismatchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\AcceptInvitationRequest;
use App\Models\Location;
use App\Models\TenantInvitation;
use App\Services\Team\TeamInvitationService;
use App\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Accepting a team invitation (central domain, Blade, throttled).
 *
 * - Any unknown, malformed, used, revoked or expired token, or a suspended
 *   tenant: one generic 404 page that reveals nothing.
 * - Signed in with the invited email: one-click accept, then the panel.
 * - Signed in with another email: explicit error, no membership.
 * - Existing account, signed out: confirms with its password (no session is
 *   started), then signs in through /app/login (MFA applies there).
 * - New account: sets name and password (Fortify rules); the email counts as
 *   verified and the user is signed in, like after registration.
 */
class InvitationController extends Controller
{
    public function show(AcceptInvitationRequest $request): Response
    {
        $invitation = $request->invitation();

        if ($invitation === null) {
            return $this->invalid();
        }

        return $this->page('invitations.show', [
            'invitation' => $invitation,
            'token' => (string) $request->route('token'),
            'mode' => $request->mode(),
            'business' => $invitation->tenant->translate($invitation->locale)?->name ?? $invitation->tenant->slug,
            'role' => $invitation->role->label(),
            'branches' => $this->branchNames($invitation),
        ]);
    }

    public function accept(AcceptInvitationRequest $request, TeamInvitationService $service): Response|RedirectResponse
    {
        $invitation = $request->invitation();

        if ($invitation === null) {
            return $this->invalid();
        }

        $business = $invitation->tenant->translate($invitation->locale)?->name ?? $invitation->tenant->slug;

        try {
            switch ($request->mode()) {
                case AcceptInvitationRequest::MODE_CONFIRM:
                    $service->acceptAsUser($invitation, $request->user());

                    return redirect()->to(Filament::getPanel('app')->getUrl($invitation->tenant));

                case AcceptInvitationRequest::MODE_PASSWORD:
                    $service->acceptWithPassword($invitation, (string) $request->validated('password'));

                    return redirect()->route('invitations.accepted')->with('invitation_business', $business);

                case AcceptInvitationRequest::MODE_REGISTER:
                    $membership = $service->acceptAsNewUser($invitation, (string) $request->validated('name'), (string) $request->validated('password'));
                    Auth::login($membership->user);
                    $request->session()->regenerate();

                    return redirect()->to(Filament::getPanel('app')->getUrl($invitation->tenant));

                default:
                    throw new InvitationEmailMismatchException;
            }
        } catch (InvitationEmailMismatchException) {
            return back()->withErrors(['invitation' => __('team.accept.errors.mismatch')]);
        } catch (ValidationException $exception) {
            if (array_key_exists('password', $exception->errors()) || array_key_exists('name', $exception->errors())) {
                throw $exception;
            }

            return back()->withErrors(['invitation' => collect($exception->errors())->flatten()->first()]);
        }
    }

    /**
     * After an existing account accepted with its password: sign in next.
     */
    public function accepted(): Response|RedirectResponse
    {
        $business = session('invitation_business');

        if (! is_string($business)) {
            return redirect()->route('login');
        }

        return $this->page('invitations.accepted', ['business' => $business]);
    }

    private function invalid(): Response
    {
        return $this->page('invitations.invalid', [], 404);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(string $view, array $data, int $status = 200): Response
    {
        return response()
            ->view($view, $data, $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex')
            ->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * @return list<string>
     */
    private function branchNames(TenantInvitation $invitation): array
    {
        $context = app(TenantContext::class);
        $previous = $context->check() ? $context->tenant() : null;
        $context->set($invitation->tenant);

        try {
            return $invitation->locations()
                ->with('translations')
                ->get()
                ->map(fn (Location $location): string => $location->translate($invitation->locale)?->name ?? $location->slug)
                ->all();
        } finally {
            $previous !== null ? $context->set($previous) : $context->clear();
        }
    }
}
