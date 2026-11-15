<?php

declare(strict_types=1);

return [

    'admin' => [
        'brand' => 'Restano Yönetim',
        'dashboard' => [
            'title' => 'Platform paneli',
        ],
        'navigation' => [
            'subscriptions' => 'Abonelik',
            'website' => 'Web sitesi',
        ],
        'fields' => [
            'is_published' => 'Yayında',
        ],
        'plans' => [
            'singular' => 'Paket',
            'plural' => 'Paketler',
            'sections' => [
                'general' => 'Genel',
                'prices' => 'Fiyatlar',
                'prices_help' => 'KDV dahil/hariç gösterimi henüz karara bağlanmadı. Boş bırakılan fiyat satılmaz ve fiyat sayfasında görünmez.',
                'limits' => 'Limitler',
                'limits_help' => 'Sayı limitlerinde boş = sınırsız, 0 = dahil değil.',
            ],
            'fields' => [
                'code' => 'Kod',
                'code_help' => 'Kalıcı tanımlayıcı (ör. starter). Oluşturulduktan sonra değiştirilemez.',
                'name' => 'Paket adı',
                'description' => 'Kısa açıklama',
                'description_help' => 'Fiyat kartında adın altında gösterilir.',
                'trial_days' => 'Deneme süresi (gün)',
                'trial_days_help' => 'Boş = platform varsayılanı.',
                'sort_order' => 'Sıra',
                'is_active' => 'Aktif',
                'is_active_help' => 'Pasif paketlere yeni abonelik başlatılamaz.',
                'is_public' => 'Fiyat sayfasında göster',
                'is_public_help' => 'Gizli paketler yalnızca elle atanır.',
                'is_featured' => 'En popüler',
                'is_featured_help' => 'Yalnızca bir paket işaretlenebilir.',
            ],
        ],
        'faqs' => [
            'singular' => 'SSS',
            'plural' => 'Sıkça sorulan sorular',
            'placements' => [
                'home' => 'Ana sayfa',
                'pricing' => 'Fiyatlar sayfası',
            ],
            'fields' => [
                'placement' => 'Gösterildiği sayfa',
                'question' => 'Soru',
                'answer' => 'Cevap',
            ],
        ],
        'testimonials' => [
            'singular' => 'Müşteri yorumu',
            'plural' => 'Müşteri yorumları',
            'fields' => [
                'author_name' => 'Kişi adı',
                'quote' => 'Yorum',
                'author_role' => 'Unvan ve işletme',
                'author_role_help' => 'Ör. Sahibi, Kiln Coffee',
            ],
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
