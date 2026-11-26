<?php

declare(strict_types=1);

/*
| /admin platform management (launch plan Faz 8): tenants, users, audit log,
| impersonation.
*/

return [

    'fields' => [
        'created_at' => 'Created',
        'is_active' => 'Active',
    ],

    'notifications' => [
        'saved' => 'Saved.',
        'deleted' => 'Deleted.',
        'restored' => 'Restored.',
    ],

    'tenants' => [
        'singular' => 'Business',
        'plural' => 'Businesses',
        'deleted' => 'Deleted',
        'no_owner' => 'No owner',
        'no_subscription' => 'No subscription',
        'members' => 'Members',
        'locations' => 'Branches',
        'payments' => [
            'title' => 'Payments',
            'created_at' => 'Date',
            'amount' => 'Amount',
            'interval' => 'Period',
            'receipt' => 'Receipt',
            'provider_reference' => 'iyzico payment ID',
            'failure' => 'Error code',
        ],
        'subscription_events' => 'Subscription history',
        'sections' => [
            'general' => 'General',
            'subscription' => 'Subscription',
            'subscription_help' => 'Read-only. Payments are applied from the owner\'s iyzico payments in /app; refunds are made in the iyzico panel.',
        ],
        'fields' => [
            'name' => 'Name',
            'owner' => 'Owner',
            'status' => 'Status',
            'plan' => 'Plan',
            'subscription' => 'Subscription',
            'slug' => 'Menu address',
            'slug_help' => 'Lowercase letters, digits and single hyphens; 3–40 characters.',
            'menu_url' => 'Menu link',
            'default_locale' => 'Default language',
            'theme' => 'Theme',
            'deleted_at' => 'Deleted',
            'access_until' => 'Menu online until',
            'trial_ends_at' => 'Trial ends',
            'period_ends_at' => 'Period ends',
            'reason' => 'Reason (optional)',
            'reason_help' => 'Written to the audit log only, never shown to the business.',
            'occurred_at' => 'Date',
            'event' => 'Event',
            'from_status' => 'Previous status',
            'to_status' => 'New status',
        ],
        'actions' => [
            'change_slug' => 'Change menu address',
            'suspend' => 'Suspend',
            'unsuspend' => 'Unsuspend',
            'delete' => 'Delete',
            'restore' => 'Restore',
        ],
        'confirm' => [
            'change_slug' => 'The menu address and the /app address change immediately. Old menu addresses redirect permanently (301) to the new one and can\'t be taken by another business. QR codes are not affected.',
            'suspend' => 'The public menu goes offline and members can\'t enter /app. Data is kept.',
            'delete' => 'The business is marked as deleted: the menu goes offline and members lose access. No data is deleted and the business can be restored.',
            'restore' => 'The business, its menu and its members\' access return to the state before deletion.',
        ],
        'errors' => [
            'slug_taken' => 'This menu address is in use or reserved for another business.',
            'slug_unchanged' => 'The new menu address is the same as the current one.',
        ],
    ],

    'subscription_events' => [
        'trial_started' => 'Trial started',
        'activated' => 'Activated',
        'renewed' => 'Renewed',
        'payment_failed' => 'Payment failed',
        'cancel_requested' => 'Cancellation requested',
        'cancel_reverted' => 'Cancellation reverted',
        'grace_started' => 'Grace period started',
        'expired' => 'Expired',
        'plan_changed' => 'Plan changed',
        'reactivated' => 'Reactivated',
        'complimentary_started' => 'Complimentary subscription started',
    ],

    'users' => [
        'singular' => 'User',
        'plural' => 'Users',
        'active' => 'Active',
        'deactivated' => 'Deactivated',
        'not_verified' => 'Not verified',
        'no_platform_role' => 'No platform role',
        'memberships' => 'Business memberships',
        'sections' => [
            'account' => 'Account',
        ],
        'fields' => [
            'name' => 'Name',
            'email' => 'Email',
            'platform_role' => 'Platform role',
            'role' => 'Role',
            'branches' => 'Branches',
            'joined' => 'Joined',
            'memberships' => 'Active memberships',
            'verified' => 'Email verified',
            'verified_at' => 'Email verified',
            'two_factor' => 'Two-factor authentication',
            'status' => 'Status',
            'deactivated_at' => 'Deactivated',
        ],
        'actions' => [
            'create_platform_user' => 'Add platform user',
            'impersonate' => 'View as this user',
            'resend_verification' => 'Resend verification email',
            'change_platform_role' => 'Change platform role',
            'deactivate' => 'Deactivate',
            'reactivate' => 'Reactivate',
        ],
        'confirm' => [
            'create_platform_user' => 'The user gets a link to set a password by email and must set up two-factor authentication at the first /admin sign-in.',
            'impersonate' => 'You will see the business panel with this user\'s permissions. The session ends automatically after :minutes minutes; start and end are written to the audit log. Password, email and two-factor changes and /admin are blocked meanwhile.',
            'change_platform_role' => 'A platform role grants /admin access. Business memberships are unchanged.',
            'deactivate' => 'The user can\'t enter any panel and an open session ends on the next request. Memberships and data are kept.',
        ],
        'notifications' => [
            'platform_user_created' => 'Platform user created; a link to set a password was sent.',
            'verification_sent' => 'Verification email queued.',
        ],
        'errors' => [
            'resend_throttled' => 'A verification email was just sent. Please try again in a minute.',
        ],
    ],

    'impersonation' => [
        'banner' => 'You are viewing as :name (:email).',
        'banner_expires' => 'ends automatically at :time',
        'leave' => 'Leave',
        'blocked' => 'This isn\'t available while viewing as another user.',
        'errors' => [
            'no_membership' => 'This user has no active business membership.',
        ],
    ],

    'audit' => [
        'singular' => 'Audit event',
        'plural' => 'Audit log',
        'fields' => [
            'created_at' => 'Time',
            'actor' => 'Actor',
            'action' => 'Action',
            'subject' => 'Subject',
            'tenant' => 'Business',
            'meta' => 'Details',
            'ip' => 'IP',
        ],
        'subjects' => [
            'tenant' => 'Business',
            'user' => 'User',
        ],
        'actions' => [
            'tenant_suspended' => 'Business suspended',
            'tenant_unsuspended' => 'Business unsuspended',
            'tenant_slug_changed' => 'Menu address changed',
            'tenant_deleted' => 'Business deleted',
            'tenant_restored' => 'Business restored',
            'user_platform_user_created' => 'Platform user created',
            'user_platform_role_changed' => 'Platform role changed',
            'user_verification_resent' => 'Verification email sent',
            'user_deactivated' => 'User deactivated',
            'user_reactivated' => 'User reactivated',
            'impersonation_started' => 'Impersonation started',
            'impersonation_ended' => 'Impersonation ended',
            'shop_order_preparing' => 'Shop order in preparation',
            'shop_order_shipped' => 'Shop order shipped',
            'shop_order_delivered' => 'Shop order delivered',
            'shop_order_cancelled' => 'Shop order cancelled',
            'shop_order_refunded' => 'Shop order refund recorded',
            'payment_bank_transfer_approved' => 'Bank transfer approved',
            'payment_bank_transfer_rejected' => 'Bank transfer rejected',
        ],
    ],

];
