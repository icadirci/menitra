<?php

declare(strict_types=1);

return [

    'features' => [
        'max_locations' => 'Şube sayısı',
        'max_menus' => 'Menü sayısı',
        'max_items' => 'Ürün sayısı',
        'max_languages' => 'Dil sayısı',
        'max_qr_codes' => 'QR kod sayısı',
        'max_tables' => 'Masa sayısı',
        'max_members' => 'Ekip üyesi sayısı',
        'premium_themes' => 'Premium temalar',
        'custom_domain' => 'Özel alan adı',
        'remove_branding' => 'Restano markasını gizleme',
        'qr_analytics' => 'QR tarama analitiği',
        'waiter_call' => 'Garson çağırma',
    ],

    'allowance' => [
        'max_locations' => ':count şube',
        'max_menus' => ':count menü',
        'max_items' => ':count ürün',
        'max_languages' => ':count dil',
        'max_qr_codes' => ':count QR kod',
        'max_tables' => ':count masa',
        'max_members' => ':count ekip üyesi',
    ],

    'allowance_unlimited' => [
        'max_locations' => 'Sınırsız şube',
        'max_menus' => 'Sınırsız menü',
        'max_items' => 'Sınırsız ürün',
        'max_languages' => 'Sınırsız dil',
        'max_qr_codes' => 'Sınırsız QR kod',
        'max_tables' => 'Sınırsız masa',
        'max_members' => 'Sınırsız ekip üyesi',
    ],

    'themes_all' => 'Tüm menü temaları (:count)',
    'themes_basic' => ':count temel menü teması',

    'errors' => [
        'invalid_transition' => 'Bu abonelik değişikliği mevcut durumda (:status) yapılamaz.',
        'live_subscription_exists' => 'Bu işletmenin zaten devam eden bir aboneliği var.',
        'limit_reached' => 'Paketinizin :feature limitine (:limit) ulaştınız.',
        'not_included' => ':feature mevcut paketinize veya abonelik durumunuza dahil değil.',
    ],

    'intervals' => [
        'monthly' => 'Aylık',
        'yearly' => 'Yıllık',
    ],

    'interval_counts' => [
        'monthly' => '{1} 1 ay|[2,*] :count ay',
        'yearly' => '{1} 1 yıl|[2,*] :count yıl',
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
