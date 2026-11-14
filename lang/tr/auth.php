<?php

declare(strict_types=1);

return [

    'failed' => 'Bu bilgilerle eşleşen bir hesap bulunamadı.',
    'password' => 'Girilen şifre hatalı.',
    'throttle' => 'Çok fazla deneme yapıldı. Lütfen :seconds saniye sonra tekrar deneyin.',

    'brand' => 'Restano',

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
        'submit' => 'Hesap oluştur',
        'have_account' => 'Zaten hesabınız var mı?',
        'login' => 'Giriş yapın',
    ],

    'forgot' => [
        'title' => 'Şifrenizi mi unuttunuz?',
        'subtitle' => 'E-posta adresinizi girin, yeni şifre belirlemeniz için bir bağlantı gönderelim.',
        'submit' => 'Bağlantı gönder',
        'back' => 'Girişe dön',
    ],

    'reset' => [
        'title' => 'Yeni şifre belirleyin',
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
