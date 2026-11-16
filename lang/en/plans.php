<?php

declare(strict_types=1);

return [

    'features' => [
        'max_locations' => 'Number of locations',
        'max_menus' => 'Number of menus',
        'max_items' => 'Number of items',
        'max_languages' => 'Number of languages',
        'max_qr_codes' => 'Number of QR codes',
        'max_members' => 'Number of team members',
        'premium_themes' => 'Premium themes',
        'custom_domain' => 'Custom domain',
        'remove_branding' => 'Remove Restano branding',
        'qr_analytics' => 'QR scan analytics',
    ],

    'allowance' => [
        'max_locations' => '{1} :count branch|[2,*] :count branches',
        'max_menus' => '{1} :count menu|[2,*] :count menus',
        'max_items' => '{1} :count product|[2,*] :count products',
        'max_languages' => '{1} :count language|[2,*] :count languages',
        'max_qr_codes' => '{1} :count QR code|[2,*] :count QR codes',
        'max_members' => '{1} :count team member|[2,*] :count team members',
    ],

    'allowance_unlimited' => [
        'max_locations' => 'Unlimited branches',
        'max_menus' => 'Unlimited menus',
        'max_items' => 'Unlimited products',
        'max_languages' => 'Unlimited languages',
        'max_qr_codes' => 'Unlimited QR codes',
        'max_members' => 'Unlimited team members',
    ],

    'themes_all' => 'All menu themes (:count)',
    'themes_basic' => '{1} :count basic menu theme|[2,*] :count basic menu themes',

    'errors' => [
        'invalid_transition' => 'This subscription change is not possible in the current status (:status).',
        'live_subscription_exists' => 'This business already has a live subscription.',
        'limit_reached' => 'You have reached your plan\'s :feature limit (:limit).',
        'not_included' => ':feature is not included in your current plan or subscription status.',
    ],

    'intervals' => [
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
    ],

    'statuses' => [
        'trialing' => 'Trial',
        'active' => 'Active',
        'past_due' => 'Payment due',
        'grace' => 'Grace period',
        'expired' => 'Expired',
    ],

    'usage' => ':used / :limit',
    'unlimited' => 'Unlimited',

];
