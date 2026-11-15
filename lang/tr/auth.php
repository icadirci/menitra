<?php

declare(strict_types=1);

return [

    'failed' => 'Bu bilgilerle eşleşen bir hesap bulunamadı.',
    'password' => 'Girilen şifre hatalı.',
    'throttle' => 'Çok fazla deneme yapıldı. Lütfen :seconds saniye sonra tekrar deneyin.',

    'brand' => 'Restano',

    'home_link' => 'Restano ana sayfası',
    'password_toggle' => 'Şifreyi göster veya gizle',

    'fields' => [
        'name' => 'Adınız',
        'email' => 'E-posta adresi',
        'password' => 'Şifre',
        'password_confirmation' => 'Şifre (tekrar)',
        'business_name' => 'İşletme adı',
        'slug' => 'Menü adresi',
    ],

    'register' => [
        'title' => 'İşletme hesabınızı oluşturun',
        'subtitle' => 'Ücretsiz denemenizi başlatın. Kredi kartı gerekmez.',
        'slug_help' => 'Menünüz :example adresinde yayınlanır. Boş bırakırsanız işletme adınızdan oluşturulur.',
        'slug_example' => 'isletmeniz',
        'slug_format' => 'Menü adresi yalnızca küçük harf, rakam ve tek tire içerebilir.',
        'slug_reserved' => 'Bu menü adresi kullanılamaz. Lütfen başka bir adres seçin.',
        'selected_plan' => 'Seçilen paket: :plan · :days gün ücretsiz deneme',
        'submit' => 'Hesap oluştur',
        'have_account' => 'Zaten hesabınız var mı?',
        'login' => 'Giriş yapın',
        'reassurance' => 'Kredi kartı gerekmez. İstediğiniz zaman iptal edin.',
        'aside_title' => 'Ürünlerinizi ekleyin, temanızı seçin, QR kodunuzu yazdırın. Misafirleriniz aynı gün menünüze ulaşsın.',
        'aside_points' => [
            'Fiyatları ve tükenen ürünleri saniyeler içinde güncelleyin',
            'Türkçe ve İngilizce menüleri tek yerden yönetin',
            'Masalarınızda hiç yeniden basmanız gerekmeyen QR kodlar',
        ],
    ],

    'forgot' => [
        'title' => 'Şifrenizi mi unuttunuz?',
        'subtitle' => 'E-posta adresinizi girin, yeni şifre belirlemeniz için bir bağlantı gönderelim.',
        'submit' => 'Bağlantı gönder',
        'back' => 'Girişe dön',
        'tip_expiry' => 'Bağlantı :minutes dakika geçerlidir ve yalnızca bir kez kullanılabilir.',
        'tip_spam' => 'Bulamadınız mı? Spam veya promosyonlar klasörünü kontrol edin.',
        'sent_title' => 'Gelen kutunuzu kontrol edin',
        'sent_subtitle' => 'Bu adrese kayıtlı bir hesap varsa şifre sıfırlama bağlantısı gönderdik.',
        'sent_steps' => [
            'Restano\'dan gelen e-postayı açın',
            'Sıfırlama bağlantısına dokunun',
            'Yeni şifrenizle giriş yapın',
        ],
        'wrong_address' => 'Adres yanlış mı?',
        'use_another' => 'Başka bir e-posta kullanın',
    ],

    'reset' => [
        'title' => 'Yeni şifre belirleyin',
        'subtitle' => 'Başka hiçbir yerde kullanmadığınız güçlü bir şifre seçin.',
        'submit' => 'Şifreyi sıfırla',
    ],

    'verify' => [
        'title' => 'E-posta adresinizi doğrulayın',
        'body' => 'E-posta adresinize bir doğrulama bağlantısı gönderdik. Hesabınızı onaylamak için bağlantıya tıklayın.',
        'sent' => 'E-posta adresinize yeni bir doğrulama bağlantısı gönderildi.',
        'resend' => 'Doğrulama e-postasını tekrar gönder',
        'logout' => 'Çıkış yap',
    ],

];
