<?php

declare(strict_types=1);

return [

    'features' => [
        'max_locations' => 'Şube sayısı',
        'max_menus' => 'Menü sayısı',
        'max_items' => 'Ürün sayısı',
        'max_languages' => 'Dil sayısı',
        'max_qr_codes' => 'QR kod sayısı',
        'premium_themes' => 'Premium temalar',
        'custom_domain' => 'Özel alan adı',
        'remove_branding' => 'Restano markasını gizleme',
        'qr_analytics' => 'QR tarama analitiği',
    ],

    'allowance' => [
        'max_locations' => ':count şube',
        'max_menus' => ':count menü',
        'max_items' => ':count ürün',
        'max_languages' => ':count dil',
        'max_qr_codes' => ':count QR kod',
    ],

    'allowance_unlimited' => [
        'max_locations' => 'Sınırsız şube',
        'max_menus' => 'Sınırsız menü',
        'max_items' => 'Sınırsız ürün',
        'max_languages' => 'Sınırsız dil',
        'max_qr_codes' => 'Sınırsız QR kod',
    ],

    'errors' => [
        'limit_reached' => 'Paketinizin :feature limitine (:limit) ulaştınız.',
        'not_included' => ':feature mevcut paketinize veya abonelik durumunuza dahil değil.',
    ],

    'intervals' => [
        'monthly' => 'Aylık',
        'yearly' => 'Yıllık',
    ],

    'statuses' => [
        'trialing' => 'Deneme',
        'active' => 'Aktif',
        'past_due' => 'Ödeme bekleniyor',
        'grace' => 'Ek süre',
        'expired' => 'Sona erdi',
    ],

    'usage' => ':used / :limit',
    'unlimited' => 'Sınırsız',

];
