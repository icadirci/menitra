<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * State-changing admin actions written to admin_audit_events.
 */
enum AdminAuditAction: string
{
    case TenantSuspended = 'tenant.suspended';
    case TenantUnsuspended = 'tenant.unsuspended';
    case TenantSlugChanged = 'tenant.slug_changed';
    case TenantDeleted = 'tenant.deleted';
    case TenantRestored = 'tenant.restored';
    case PlatformUserCreated = 'user.platform_user_created';
    case PlatformRoleChanged = 'user.platform_role_changed';
    case VerificationResent = 'user.verification_resent';
    case UserDeactivated = 'user.deactivated';
    case UserReactivated = 'user.reactivated';
    case ImpersonationStarted = 'impersonation.started';
    case ImpersonationEnded = 'impersonation.ended';

    public function label(): string
    {
        return __('admin.audit.actions.'.str_replace('.', '_', $this->value));
    }
}
