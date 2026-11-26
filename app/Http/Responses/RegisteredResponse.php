<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Filament\App\Pages\Checkout;
use App\Filament\App\Pages\ShopCheckout;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Shop\ShopCart;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * After registration: "start=pay" goes to the new business' payment page,
 * picked QR stands to the shop checkout (with "pay", the stands wait in the
 * cart and are offered after the plan payment), anything else to the panel (Fortify's default). The payment page needs a
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

        if ($membership?->tenant !== null) {
            $hasStands = $this->fillCart($membership->tenant, $user, $request->input('stands'));

            if ($hasStands && $request->input('start') !== 'pay') {
                return redirect()->to(ShopCheckout::getUrl(panel: 'app', tenant: $membership->tenant));
            }
        }

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

    /**
     * QR stands picked on the form go into the new business' cart. The tenant
     * is the user's own (membership); the context is restored afterwards.
     */
    private function fillCart(Tenant $tenant, User $user, mixed $selection): bool
    {
        $context = app(TenantContext::class);
        $previous = $context->check() ? $context->tenant() : null;
        $context->set($tenant);

        try {
            $cart = app(ShopCart::class);
            $cart->seed($tenant, $user, $selection);

            return $cart->quantity() > 0;
        } finally {
            $previous !== null ? $context->set($previous) : $context->clear();
        }
    }
}
