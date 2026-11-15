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

    'errors' => [
        'limit_reached' => 'Paketinizin :feature limitine (:limit) ulaştınız.',
        'not_included' => ':feature mevcut paketinize veya abonelik durumunuza dahil değil.',
    ],

    'intervals' => [
        'monthly' => 'Aylık',
        'yearly' => 'Yıllık',
    ],

    'usage' => ':used / :limit',
    'unlimited' => 'Sınırsız',

];
