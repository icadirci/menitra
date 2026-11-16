<?php

declare(strict_types=1);

return [

    'members' => [
        'singular' => 'Team member',
        'plural' => 'Team',
        'navigation' => 'Team',
    ],

    'invitations' => [
        'singular' => 'Invitation',
        'plural' => 'Invitations',
        'navigation' => 'Invitations',
        'empty' => 'No open invitations',
        'statuses' => [
            'pending' => 'Pending',
            'expired' => 'Expired',
            'accepted' => 'Accepted',
            'revoked' => 'Revoked',
        ],
    ],

    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'role' => 'Role',
        'role_help' => 'Managers run their assigned branches and those branches\' menus; branch staff see stock and QR codes.',
        'locations' => 'Branches',
        'locations_help' => 'Choose at least one branch. The member reaches only the selected branches.',
        'is_active' => 'Active',
        'joined' => 'Joined',
        'status' => 'Status',
        'expires_at' => 'Expires',
    ],

    'actions' => [
        'invite' => 'Invite team member',
        'send' => 'Send invitation',
        'edit' => 'Role and branches',
        'deactivate' => 'Deactivate',
        'activate' => 'Activate',
        'remove' => 'Remove from team',
        'resend' => 'Resend',
        'revoke' => 'Revoke',
    ],

    'confirm' => [
        'deactivate' => 'The member loses access to this business immediately. You can activate them again later.',
        'remove' => 'The membership and branch assignments are deleted; the user\'s account stays. Adding them again needs a new invitation.',
        'resend' => 'A new link is sent; the previous link stops working.',
        'revoke' => 'The invitation link stops working.',
    ],

    'notifications' => [
        'invited' => 'Invitation sent',
        'resent' => 'Invitation sent again',
        'revoked' => 'Invitation revoked',
        'saved' => 'Saved',
        'removed' => 'Member removed from the team',
    ],

    'errors' => [
        'email' => 'Enter a valid email address.',
        'role' => 'Choose the manager or branch staff role.',
        'locations' => 'Choose at least one of your business\'s branches.',
        'already_member' => 'This email address is already on your team.',
        'already_invited' => 'There is an open invitation for this address. Use the Invitations page to resend it.',
        'invitation_closed' => 'This invitation is no longer open.',
        'resend_too_soon' => 'Wait a minute before resending the invitation.',
        'owner_locked' => 'The owner\'s membership can\'t be changed here.',
    ],

    'all_locations' => 'All branches',
    'no_locations' => 'No branches',
    'usage' => 'Team members and pending invitations: :usage',
    'limit_reached' => 'You have reached your plan\'s team member limit.',
    'invite_help' => 'The invitation link is valid for :days days.',

    'mail' => [
        'subject' => 'You\'re invited to join :business',
        'greeting' => 'Hello,',
        'intro' => ':inviter invited you to join the :business team on Restano as :role.',
        'cta' => 'View invitation',
        'expiry' => '{1} The link is valid for :count day and can be used once.|[2,*] The link is valid for :count days and can be used once.',
        'outro' => 'If you weren\'t expecting this invitation, you can ignore this email.',
    ],

    'accept' => [
        'title' => 'Join :business',
        'subtitle' => 'Your role: :role',
        'branches' => 'Branches you will reach',
        'submit' => 'Accept invitation',
        'submit_new' => 'Create account and join',
        'existing_account' => 'You already have a Restano account with :email. Enter your password to accept.',
        'new_account' => 'Create a Restano account for :email.',
        'forgot_password' => 'Forgot password',
        'logout' => 'Sign out',
        'home' => 'Back to the home page',
        'invalid' => [
            'title' => 'Invalid invitation link',
            'body' => 'This link may have expired, been revoked or already been used. Contact the business for a new invitation.',
        ],
        'accepted' => [
            'title' => 'Invitation accepted',
            'body' => 'You are now on the :business team. Sign in to continue.',
            'login' => 'Sign in',
        ],
        'errors' => [
            'mismatch' => 'This invitation was sent to another email address. Sign out to sign in with the invited address.',
            'password' => 'The password is incorrect.',
            'account_exists' => 'An account with this email address already exists.',
            'already_member' => 'You are already a member of this business.',
            'no_locations' => 'The branches in this invitation no longer exist. Contact the business for a new invitation.',
        ],
    ],

];
