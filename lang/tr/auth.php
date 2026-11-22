<?php

declare(strict_types=1);

return [

    'failed' => 'Bu bilgilerle eşleşen bir hesap bulunamadı.',
    'password' => 'Girilen şifre hatalı.',
    'throttle' => 'Çok fazla deneme yapıldı. Lütfen :seconds saniye sonra tekrar deneyin.',

    'brand' => 'Menitra',

    'home_link' => 'Menitra ana sayfası',
    'password_toggle' => 'Şifreyi göster veya gizle',

    'fields' => [
        'name' => 'Adınız',
        'email' => 'E-posta adresi',
        'password' => 'Şifre',
        'password_confirmation' => 'Şifre (tekrar)',
        'business_name' => 'İşletme adı',
        'slug' => 'Menü adresi',
    ],

    // Kullanım koşulları onayı (kayıt ve davet ile yeni hesap). Aydınlatma
    // metni bir onay değil, bilgilendirmedir: sadece not olarak gösterilir.
    'terms' => [
        'accept_before' => '',
        'accept_link' => 'Kullanım koşullarını',
        'accept_after' => ' okudum ve kabul ediyorum.',
        'privacy_before' => 'Kişisel verilerinizin nasıl işlendiği ',
        'privacy_link' => 'Aydınlatma Metni',
        'privacy_after' => '\'nde açıklanmıştır.',
        'new_tab' => '(yeni sekmede açılır)',
        'required' => 'Devam etmek için kullanım koşullarını kabul etmelisiniz.',
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
            'Türkçe, İngilizce ve Arapça menüleri tek yerden yönetin',
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
            'Menitra\'dan gelen e-postayı açın',
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

    'mail' => [
        'greeting' => 'Merhaba :name,',
        'fallback' => 'Düğme çalışmazsa bu bağlantıyı tarayıcınıza yapıştırın:',
        'signature' => 'Menitra ekibi',
        'verify' => [
            'subject' => 'E-posta adresinizi doğrulayın',
            'intro' => 'Menitra hesabınızı etkinleştirmek için e-posta adresinizi doğrulayın.',
            'cta' => 'E-posta adresimi doğrula',
            'expiry' => 'Bağlantı :count dakika geçerlidir.',
            'outro' => 'Bu hesabı siz oluşturmadıysanız bu e-postayı yok sayabilirsiniz.',
        ],
        'reset' => [
            'subject' => 'Şifre sıfırlama bağlantınız',
            'intro' => 'Hesabınız için bir şifre sıfırlama talebi aldık.',
            'cta' => 'Yeni şifre belirle',
            'expiry' => 'Bağlantı :count dakika geçerlidir ve bir kez kullanılabilir.',
            'outro' => 'Bu talebi siz yapmadıysanız bu e-postayı yok sayabilirsiniz; şifreniz değişmez.',
        ],
    ],

];
