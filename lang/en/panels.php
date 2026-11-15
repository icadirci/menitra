<?php

declare(strict_types=1);

return [

    'admin' => [
        'brand' => 'Restano Admin',
        'dashboard' => [
            'title' => 'Platform dashboard',
        ],
    ],

    'app' => [
        'brand' => 'Restano Business',
        'dashboard' => [
            'title' => 'Dashboard',
        ],
        'navigation' => [
            'menu_management' => 'Menu management',
            'locations' => 'Locations',
            'business' => 'Business',
        ],
        'locales' => [
            'navigation' => 'Languages',
            'title' => 'Languages',
            'subheading' => 'The languages your menu is published in. Plan usage: :usage',
            'limit_reached' => 'You have reached your plan\'s language limit.',
            'columns' => [
                'language' => 'Language',
                'code' => 'Code',
            ],
            'actions' => [
                'add' => 'Add language',
                'make_default' => 'Make default',
                'remove' => 'Remove',
            ],
            'confirm' => [
                'make_default' => 'This language becomes the fallback for content that has no translation.',
                'remove' => 'The language is removed from your menu. Its translations are not deleted and come back when you add it again.',
            ],
            'notifications' => [
                'added' => 'Language added.',
                'default_changed' => 'Default language changed.',
                'removed' => 'Language removed. Translations are kept.',
            ],
        ],
        'translations' => [
            'label' => 'Translations',
            'default' => 'Default',
        ],
        'media' => [
            'invalid_type' => 'Only JPEG, PNG or WebP images can be uploaded.',
        ],
        'money' => [
            'invalid' => 'Enter a valid amount (e.g. 85 or 85.50).',
            'negative' => 'The amount cannot be negative.',
        ],
    ],

];
