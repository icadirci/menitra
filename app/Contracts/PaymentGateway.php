<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\SubscriptionStatus;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provider-agnostic payment boundary (docs/architecture/subscriptions.md
 * § Payment architecture). No implementation exists yet; the adapter (iyzico)
 * comes with the payment phase and translates provider events into
 * SubscriptionService calls (activate, renew, markPastDue, resubscribe).
 * Lifecycle logic never lives in an adapter.
 */
interface PaymentGateway
{
    /**
     * Starts a checkout for the price and returns the URL the customer is sent to.
     */
    public function createCheckout(Tenant $tenant, PlanPrice $price, string $returnUrl): string;

    /**
     * Stops renewal at the provider (the period runs out; SubscriptionService::cancel()).
     */
    public function cancel(Subscription $subscription): void;

    /**
     * Verifies a provider webhook and applies it through SubscriptionService.
     */
    public function handleWebhook(Request $request): Response;

    /**
     * The provider's view of the subscription, for reconciliation.
     */
    public function fetchStatus(Subscription $subscription): SubscriptionStatus;
}
