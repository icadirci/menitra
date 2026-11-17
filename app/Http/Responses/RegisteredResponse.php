<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Filament\App\Pages\Checkout;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * After registration: "start=pay" goes to the new business' payment page,
 * anything else to the panel (Fortify's default). The payment page needs a
 * verified email, so an unverified user is sent to /email/verify first and
 * returns to the payment page after verifying (the intended URL). The
 * business comes from the user's own membership, never from the request.
 */
class RegisteredResponse implements RegisterResponse
{
    public function toResponse($request): BaseResponse
    {
        /** @var Request $request */
        if ($request->wantsJson()) {
            return new JsonResponse('', Response::HTTP_CREATED);
        }

        $user = $request->user();
        $membership = $user instanceof User ? $user->memberships()->with('tenant')->first() : null;

        if ($request->input('start') === 'pay' && $membership?->tenant !== null) {
            $plan = $request->input('plan');

            return redirect()->to(Checkout::getUrl(
                is_string($plan) && $plan !== '' ? ['plan' => $plan] : [],
                panel: 'app',
                tenant: $membership->tenant,
            ));
        }

        return redirect()->intended(Fortify::redirects('register'));
    }
}
