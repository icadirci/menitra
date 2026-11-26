<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Filament\App\Pages\ShopOrders;
use App\Filament\App\Pages\ViewSubscription;
use App\Services\Billing\PaymentProcessor;
use App\Services\Billing\PaymentTokenResolver;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * POST /billing/iyzico/callback: iyzico sends the customer's browser back with
 * the checkout token (cross-site POST, so no session cookie arrives). The
 * route has no session and no CSRF (routes/billing.php): starting a session
 * here would overwrite the customer's session cookie.
 *
 * Nothing posted is trusted: the token only finds the payment
 * (PaymentTokenResolver), the result comes from the provider's API
 * (PaymentProcessor). The browser then goes (303, GET, cookies sent) to the
 * subscription page (shop orders: the orders page), which shows the payment's state through the normal
 * panel checks.
 */
class IyzicoCallbackController
{
    public function __invoke(Request $request, PaymentTokenResolver $resolver, PaymentProcessor $processor, TenantContext $context): RedirectResponse
    {
        $payment = $resolver->resolve($request->input('token'));

        if ($payment === null) {
            return $this->noStore(redirect()->to('/app', 303));
        }

        $tenant = $payment->tenant;
        $context->set($tenant);

        try {
            $processor->complete($payment);
        } catch (Throwable $exception) {
            // Provider unreachable or a bad answer: the payment stays pending and
            // the webhook / reconciliation settles it; the page says so.
            report($exception);
        } finally {
            $context->clear();
        }

        return $this->noStore(redirect()->to(
            ($payment->isShopOrder() ? ShopOrders::class : ViewSubscription::class)::getUrl(['payment' => $payment->public_id], panel: 'app', tenant: $tenant),
            303,
        ));
    }

    private function noStore(RedirectResponse $response): RedirectResponse
    {
        return $response->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
