<?php

declare(strict_types=1);

return [

    'title' => 'Hesabım',
    'subtitle' => ':business için hesap özeti',
    'open_panel' => 'İşletme paneline git',
    'logout' => 'Çıkış yap',
    'home_link' => 'Ana sayfa',

    'menu' => [
        'heading' => 'Menü durumu',
        'online' => 'Menünüz yayında',
        'offline' => 'Menünüz şu an yayında değil',
        'online_until' => 'Menünüz :date tarihine kadar yayında kalır.',
    ],

    'subscription' => [
        'heading' => 'Abonelik',
        'plan' => 'Plan',
        'status' => 'Durum',
        'none' => 'Abonelik yok',
        'access_until' => 'Menü yayında kalır',
        'ended_at' => 'Sona erdi',
        'days_left' => '{0} Bugün sona eriyor|{1} 1 gün kaldı|[2,*] :days gün kaldı',
        'key_dates' => [
            'trial_ends_at' => 'Deneme bitişi',
            'current_period_ends_at' => 'Dönem bitişi',
            'grace_ends_at' => 'Ek süre bitişi',
        ],
        'cancel_at_period_end' => 'Aboneliğiniz dönem sonunda iptal edilecek.',
        'past_due' => 'Son ödemeniz alınamadı. Dönem sonuna kadar menünüz yayında kalır.',
    ],

    'grace' => [
        'title' => 'Ek süredesiniz',
        'body' => 'Deneme veya abonelik döneminiz sona erdi. Menünüz :date tarihine kadar yayında kalacak; sonrasında misafirleriniz menünüzü göremeyecek.',
        'action' => 'Kesintiye uğramamak için bizimle iletişime geçin.',
    ],

    'expired' => [
        'title' => 'Menünüz kapalı',
        'body' => 'Aktif bir aboneliğiniz olmadığı için menünüz misafirlerinize gösterilmiyor. Menüleriniz, ürünleriniz ve QR kodlarınız dahil tüm verileriniz korunuyor.',
        'reactivate' => 'Menünüzü yeniden yayına almak için bizimle iletişime geçin.',
        'contact' => 'İletişim sayfası',
        'email' => 'E-posta',
        'phone' => 'Telefon',
        'whatsapp' => 'WhatsApp',
        'support_hours' => 'Destek saatleri',
    ],

    'staff_offline' => 'Menü şu an misafirlere gösterilmiyor. Ayrıntılar için işletme sahibinizle iletişime geçin.',

    'links' => [
        'heading' => 'Menü bağlantıları',
        'public_menu' => 'Menünüzün adresi',
        'branch_menu' => 'Şube menüsü',
        'default' => 'Varsayılan şube',
        'inactive' => 'Pasif şube: menüsü misafirlere gösterilmez',
        'none' => 'Size atanmış bir şube yok.',
        'qr_link' => 'QR bağlantısı',
        'qr_alt' => ':branch şubesinin QR kodu',
        'qr_help' => 'Bu bağlantı hiç değişmez; basılı QR kodlarınız her zaman güncel menünüze yönlenir.',
        'no_qr' => 'Bu şubenin aktif bir QR kodu yok.',
        'download_svg' => 'SVG indir',
        'download_png' => 'PNG indir',
    ],

    'unavailable' => [
        'suspended' => [
            'title' => 'Hesap askıya alındı',
        ],
        'no_business' => [
            'title' => 'Bağlı işletme yok',
            'body' => 'Hesabınız henüz bir işletmeye bağlı değil. Bir işletme tarafından davet edildiyseniz işletme sahibiyle, aksi halde bizimle iletişime geçin.',
        ],
        'contact' => 'Bizimle iletişime geçin',
    ],

];
