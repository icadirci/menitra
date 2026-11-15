<?php

declare(strict_types=1);

return [

    'admin' => [
        'brand' => 'Restano Admin',
        'dashboard' => [
            'title' => 'Platform dashboard',
        ],
        'navigation' => [
            'subscriptions' => 'Subscriptions',
            'website' => 'Website',
        ],
        'fields' => [
            'is_published' => 'Published',
        ],
        'plans' => [
            'singular' => 'Plan',
            'plural' => 'Plans',
            'sections' => [
                'general' => 'General',
                'prices' => 'Prices',
                'prices_help' => 'Whether prices are shown VAT-inclusive is not decided yet. A price left empty is not sold and is hidden on the pricing page.',
                'limits' => 'Limits',
                'limits_help' => 'Count limits: empty = unlimited, 0 = not included.',
            ],
            'fields' => [
                'code' => 'Code',
                'code_help' => 'Permanent identifier (e.g. starter). Cannot be changed after creation.',
                'name' => 'Plan name',
                'description' => 'Short description',
                'description_help' => 'Shown under the name on the pricing card.',
                'trial_days' => 'Trial length (days)',
                'trial_days_help' => 'Empty = platform default.',
                'sort_order' => 'Order',
                'is_active' => 'Active',
                'is_active_help' => 'New subscriptions cannot start on inactive plans.',
                'is_public' => 'Show on pricing page',
                'is_public_help' => 'Hidden plans are assigned manually only.',
                'is_featured' => 'Most popular',
                'is_featured_help' => 'Only one plan can be marked.',
            ],
        ],
        'faqs' => [
            'singular' => 'FAQ',
            'plural' => 'FAQ',
            'placements' => [
                'home' => 'Home page',
                'pricing' => 'Pricing page',
            ],
            'fields' => [
                'placement' => 'Shown on',
                'question' => 'Question',
                'answer' => 'Answer',
            ],
        ],
        'testimonials' => [
            'singular' => 'Testimonial',
            'plural' => 'Testimonials',
            'fields' => [
                'author_name' => 'Name',
                'quote' => 'Quote',
                'author_role' => 'Role and business',
                'author_role_help' => 'E.g. Owner, Kiln Coffee',
            ],
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
