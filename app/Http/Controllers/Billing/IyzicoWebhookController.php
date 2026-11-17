<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Contracts\PaymentGateway;
use App\Jobs\ProcessPayment;
use App\Services\Billing\PaymentTokenResolver;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /billing/iyzico/webhook: server-to-server notification
 * (X-IYZ-SIGNATURE-V3). Even a valid notification only triggers a
 * ProcessPayment job, which asks the provider's API for the result; the
 * payload's status is never applied. Unknown or foreign tokens are
 * acknowledged (2xx stops iyzico's retries) and ignored.
 */
class IyzicoWebhookController
{
    public function __invoke(Request $request, PaymentGateway $gateway, PaymentTokenResolver $resolver, TenantContext $context): Response
    {
        $notification = $gateway->parseWebhook($request);

        if ($notification === null) {
            return response('', 403);
        }

        $payment = $resolver->resolve($notification->token);

        if ($payment === null || $payment->public_id !== $notification->reference || $payment->isSucceeded()) {
            return response('', 200);
        }

        $context->set($payment->tenant);

        try {
            ProcessPayment::dispatch($payment->getKey());
        } finally {
            $context->clear();
        }

        return response('', 200);
    }
}
