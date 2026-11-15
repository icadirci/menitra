<?php

declare(strict_types=1);

return [

    'title' => 'My account',
    'subtitle' => 'Account summary for :business',
    'open_panel' => 'Go to the business panel',
    'logout' => 'Log out',
    'home_link' => 'Home page',

    'menu' => [
        'heading' => 'Menu status',
        'online' => 'Your menu is online',
        'offline' => 'Your menu is not online right now',
        'online_until' => 'Your menu stays online until :date.',
    ],

    'subscription' => [
        'heading' => 'Subscription',
        'plan' => 'Plan',
        'status' => 'Status',
        'none' => 'No subscription',
        'access_until' => 'Menu stays online until',
        'ended_at' => 'Ended on',
        'days_left' => '{0} Ends today|{1} 1 day left|[2,*] :days days left',
        'key_dates' => [
            'trial_ends_at' => 'Trial ends',
            'current_period_ends_at' => 'Period ends',
            'grace_ends_at' => 'Grace period ends',
        ],
        'cancel_at_period_end' => 'Your subscription will be cancelled at the end of the period.',
        'past_due' => 'Your last payment failed. Your menu stays online until the end of the period.',
    ],

    'grace' => [
        'title' => 'You are in your grace period',
        'body' => 'Your trial or subscription period has ended. Your menu stays online until :date; after that your guests will no longer see it.',
        'action' => 'Contact us to avoid an interruption.',
    ],

    'expired' => [
        'title' => 'Your menu is offline',
        'body' => 'Your menu is not shown to guests because you have no active subscription. All your data, including menus, items and QR codes, is kept safe.',
        'reactivate' => 'Contact us to bring your menu back online.',
        'contact' => 'Contact page',
        'email' => 'Email',
        'phone' => 'Phone',
        'whatsapp' => 'WhatsApp',
        'support_hours' => 'Support hours',
    ],

    'staff_offline' => 'The menu is not shown to guests right now. Please contact your business owner for details.',

    'links' => [
        'heading' => 'Menu links',
        'public_menu' => 'Your menu address',
        'branch_menu' => 'Branch menu',
        'default' => 'Default branch',
        'inactive' => 'Inactive branch: its menu is not shown to guests',
        'none' => 'No branch is assigned to you.',
        'qr_link' => 'QR link',
        'qr_alt' => 'QR code of :branch',
        'qr_help' => 'This link never changes; printed QR codes always lead to your current menu.',
        'no_qr' => 'This branch has no active QR code.',
        'download_svg' => 'Download SVG',
        'download_png' => 'Download PNG',
    ],

    'unavailable' => [
        'suspended' => [
            'title' => 'Account suspended',
        ],
        'no_business' => [
            'title' => 'No business linked',
            'body' => 'Your account is not linked to a business yet. If a business invited you, contact its owner; otherwise contact us.',
        ],
        'contact' => 'Contact us',
    ],

];
