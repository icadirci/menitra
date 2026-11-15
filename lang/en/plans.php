<?php

declare(strict_types=1);

return [

    'features' => [
        'max_locations' => 'Number of locations',
        'max_menus' => 'Number of menus',
        'max_items' => 'Number of items',
        'max_languages' => 'Number of languages',
        'max_qr_codes' => 'Number of QR codes',
        'premium_themes' => 'Premium themes',
        'custom_domain' => 'Custom domain',
        'remove_branding' => 'Remove Restano branding',
        'qr_analytics' => 'QR scan analytics',
    ],

    'errors' => [
        'limit_reached' => 'You have reached your plan\'s :feature limit (:limit).',
        'not_included' => ':feature is not included in your current plan or subscription status.',
    ],

    'intervals' => [
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
    ],

    'usage' => ':used / :limit',
    'unlimited' => 'Unlimited',

];
