<?php

declare(strict_types=1);

return [

    'admin' => [
        'brand' => 'Restano Yönetim',
        'dashboard' => [
            'title' => 'Platform paneli',
        ],
    ],

    'app' => [
        'brand' => 'Restano İşletme',
        'dashboard' => [
            'title' => 'Panel',
        ],
        'navigation' => [
            'menu_management' => 'Menü Yönetimi',
            'locations' => 'Şubeler',
            'business' => 'İşletme',
        ],
        'locales' => [
            'navigation' => 'Diller',
            'title' => 'Diller',
            'subheading' => 'Menünüzün yayınlanacağı diller. Paket kullanımı: :usage',
            'limit_reached' => 'Paketinizin dil limitine ulaştınız.',
            'columns' => [
                'language' => 'Dil',
                'code' => 'Kod',
            ],
            'actions' => [
                'add' => 'Dil ekle',
                'make_default' => 'Varsayılan yap',
                'remove' => 'Kaldır',
            ],
            'confirm' => [
                'make_default' => 'Bu dil, çevirisi eksik içerikler için yedek dil olarak kullanılacak.',
                'remove' => 'Dil menünüzden kaldırılır. Bu dildeki çeviriler silinmez; dili yeniden eklediğinizde geri gelir.',
            ],
            'notifications' => [
                'added' => 'Dil eklendi.',
                'default_changed' => 'Varsayılan dil değiştirildi.',
                'removed' => 'Dil kaldırıldı. Çeviriler korunuyor.',
            ],
        ],
        'translations' => [
            'label' => 'Çeviriler',
            'default' => 'Varsayılan',
        ],
        'media' => [
            'invalid_type' => 'Yalnızca JPEG, PNG veya WebP görselleri yükleyebilirsiniz.',
        ],
        'money' => [
            'invalid' => 'Geçerli bir tutar girin (örn. 85 veya 85,50).',
            'negative' => 'Tutar negatif olamaz.',
        ],
    ],

];
