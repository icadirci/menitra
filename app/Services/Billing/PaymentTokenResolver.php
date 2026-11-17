<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Payment;
use App\Models\Tenant;

/**
 * Provider checkout token → its payment, or null.
 * Documented scope bypass (docs/architecture/tenancy.md): the provider's
 * browser callback and webhook carry no session, so the token, which only
 * the provider and our payment row know, identifies the tenant (like a QR
 * code). The tenant is loaded even when suspended or soft-deleted: money that
 * was taken is always recorded. Callers set TenantContext from the result and
 * do everything else through the normal scope.
 */
final class PaymentTokenResolver
{
    /** iyzico tokens are UUID-like; anything else is never looked up. */
    public const string TOKEN_PATTERN = '[A-Za-z0-9-]{16,100}';

    public function resolve(mixed $token): ?Payment
    {
        if (! is_string($token) || preg_match('/^'.self::TOKEN_PATTERN.'$/', $token) !== 1) {
            return null;
        }

        $payment = Payment::withoutTenantScope()->where('provider_token', $token)->first();

        if ($payment === null || ! hash_equals((string) $payment->provider_token, $token)) {
            return null;
        }

        $tenant = Tenant::withTrashed()->find($payment->tenant_id);

        return $tenant === null ? null : $payment->setRelation('tenant', $tenant);
    }
}
