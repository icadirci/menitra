<?php

declare(strict_types=1);

namespace App\Services\Team;

use App\Models\Tenant;
use App\Models\TenantInvitation;

/**
 * /invitations/{token} → the pending invitation, or null.
 * Documented scope bypass: the token identifies the tenant (like a QR code).
 *
 * Only the SHA-256 hash is looked up, then compared again in constant time.
 * Unknown, malformed, accepted, revoked and expired tokens, and invitations
 * of suspended or deleted tenants, all return null, so the caller shows one
 * generic page that reveals nothing.
 */
final class InvitationTokenResolver
{
    /** URL-safe base64 of 32 random bytes. */
    public const string TOKEN_PATTERN = '[A-Za-z0-9_-]{43}';

    public function resolve(string $token): ?TenantInvitation
    {
        if (preg_match('/^'.self::TOKEN_PATTERN.'$/', $token) !== 1) {
            return null;
        }

        $hash = TenantInvitation::hashToken($token);
        $invitation = TenantInvitation::withoutTenantScope()->where('token_hash', $hash)->first();

        if ($invitation === null || ! hash_equals($invitation->token_hash, $hash) || ! $invitation->isPending()) {
            return null;
        }

        $tenant = Tenant::query()->with('translations')->find($invitation->tenant_id);

        if ($tenant === null || ! $tenant->isActive()) {
            return null;
        }

        return $invitation->setRelation('tenant', $tenant);
    }
}
