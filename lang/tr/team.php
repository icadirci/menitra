<?php

declare(strict_types=1);

return [

    'members' => [
        'singular' => 'Ekip üyesi',
        'plural' => 'Ekip',
        'navigation' => 'Ekip',
    ],

    'invitations' => [
        'singular' => 'Davet',
        'plural' => 'Davetler',
        'navigation' => 'Davetler',
        'empty' => 'Açık davet yok',
        'statuses' => [
            'pending' => 'Bekliyor',
            'expired' => 'Süresi doldu',
            'accepted' => 'Kabul edildi',
            'revoked' => 'İptal edildi',
        ],
    ],

    'fields' => [
        'name' => 'Ad',
        'email' => 'E-posta',
        'role' => 'Rol',
        'role_help' => 'Müdürler atandıkları şubeleri ve o şubelerin menülerini yönetir; şube personeli stok ve QR kodlarını görür.',
        'locations' => 'Şubeler',
        'locations_help' => 'En az bir şube seçin. Üye sadece seçilen şubelere erişir.',
        'is_active' => 'Aktif',
        'joined' => 'Katılma',
        'status' => 'Durum',
        'expires_at' => 'Son geçerlilik',
    ],

    'actions' => [
        'invite' => 'Ekip üyesi davet et',
        'send' => 'Daveti gönder',
        'edit' => 'Rol ve şubeler',
        'deactivate' => 'Pasifleştir',
        'activate' => 'Aktifleştir',
        'remove' => 'Ekipten çıkar',
        'resend' => 'Tekrar gönder',
        'revoke' => 'İptal et',
    ],

    'confirm' => [
        'deactivate' => 'Üyenin bu işletmeye erişimi hemen kesilir. Daha sonra tekrar aktifleştirebilirsiniz.',
        'remove' => 'Üyelik ve şube atamaları silinir; kullanıcının hesabı kalır. Tekrar eklemek için yeni davet gerekir.',
        'resend' => 'Yeni bir bağlantı gönderilir; önceki bağlantı artık çalışmaz.',
        'revoke' => 'Davet bağlantısı artık çalışmaz.',
    ],

    'notifications' => [
        'invited' => 'Davet gönderildi',
        'resent' => 'Davet tekrar gönderildi',
        'revoked' => 'Davet iptal edildi',
        'saved' => 'Kaydedildi',
        'removed' => 'Üye ekipten çıkarıldı',
    ],

    'errors' => [
        'email' => 'Geçerli bir e-posta adresi girin.',
        'role' => 'Müdür veya şube personeli rolünü seçin.',
        'locations' => 'İşletmenize ait en az bir şube seçin.',
        'already_member' => 'Bu e-posta adresi zaten ekibinizde.',
        'already_invited' => 'Bu adrese gönderilmiş açık bir davet var. Tekrar göndermek için Davetler sayfasını kullanın.',
        'invitation_closed' => 'Bu davet artık açık değil.',
        'resend_too_soon' => 'Daveti tekrar göndermek için bir dakika bekleyin.',
        'owner_locked' => 'İşletme sahibinin üyeliği buradan değiştirilemez.',
    ],

    'all_locations' => 'Tüm şubeler',
    'no_locations' => 'Şube yok',
    'usage' => 'Ekip üyeleri ve bekleyen davetler: :usage',
    'limit_reached' => 'Paketinizin ekip üyesi limitine ulaştınız.',
    'invite_help' => 'Davet bağlantısı :days gün geçerlidir.',

    'mail' => [
        'subject' => ':business ekibine davet edildiniz',
        'greeting' => 'Merhaba,',
        'intro' => ':inviter sizi Menitra üzerinde :business ekibine :role olarak davet etti.',
        'cta' => 'Daveti görüntüle',
        'expiry' => 'Bağlantı :count gün geçerlidir ve bir kez kullanılabilir.',
        'outro' => 'Bu daveti beklemiyorsanız e-postayı yok sayabilirsiniz.',
    ],

    'accept' => [
        'title' => ':business ekibine katılın',
        'subtitle' => 'Rolünüz: :role',
        'branches' => 'Erişeceğiniz şubeler',
        'submit' => 'Daveti kabul et',
        'submit_new' => 'Hesap oluştur ve katıl',
        'existing_account' => ':email adresiyle bir Menitra hesabınız var. Kabul etmek için şifrenizi girin.',
        'new_account' => ':email adresi için bir Menitra hesabı oluşturun.',
        'forgot_password' => 'Şifremi unuttum',
        'logout' => 'Çıkış yap',
        'home' => 'Ana sayfaya dön',
        'invalid' => [
            'title' => 'Davet bağlantısı geçersiz',
            'body' => 'Bu bağlantının süresi dolmuş, iptal edilmiş veya daha önce kullanılmış olabilir. Yeni bir davet için işletmeyle iletişime geçin.',
        ],
        'accepted' => [
            'title' => 'Davet kabul edildi',
            'body' => 'Artık :business ekibindesiniz. Devam etmek için giriş yapın.',
            'login' => 'Giriş yap',
        ],
        'errors' => [
            'mismatch' => 'Bu davet başka bir e-posta adresine gönderildi. Davet edilen adresle giriş yapmak için çıkış yapın.',
            'password' => 'Şifre hatalı.',
            'account_exists' => 'Bu e-posta adresiyle zaten bir hesap var.',
            'already_member' => 'Bu işletmenin zaten üyesisiniz.',
            'no_locations' => 'Davetteki şubeler artık mevcut değil. Yeni bir davet için işletmeyle iletişime geçin.',
        ],
    ],

];
