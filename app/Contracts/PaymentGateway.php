<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\PaymentGatewayException;
use App\Support\Billing\CheckoutRequest;
use App\Support\Billing\CheckoutResult;
use App\Support\Billing\CheckoutSession;
use App\Support\Billing\WebhookNotification;
use Illuminate\Http\Request;

/**
 * Provider boundary for manual period payments (docs/architecture/subscriptions.md
 * § Payments). The adapter (IyzicoGateway) only talks to the provider and
 * verifies its signatures; amounts, periods and subscription transitions are
 * decided by CheckoutService / PaymentProcessor and SubscriptionService.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Starts a hosted checkout and returns the page the customer is sent to.
     *
     * @throws PaymentGatewayException
     */
    public function initializeCheckout(CheckoutRequest $request): CheckoutSession;

    /**
     * The provider's verified result for a checkout token. Never trust
     * anything posted by the browser; this is the only source of truth.
     * $reference is the payment's public id: iyzico's retrieve API echoes the
     * request's conversationId (not the one sent at initialize), so it is
     * passed along and comes back inside the signed response.
     *
     * @throws PaymentGatewayException
     */
    public function retrieveCheckout(string $token, string $reference): CheckoutResult;

    /**
     * Verifies a webhook notification; null when its signature is invalid or
     * it isn't a checkout notification.
     */
    public function parseWebhook(Request $request): ?WebhookNotification;
}
