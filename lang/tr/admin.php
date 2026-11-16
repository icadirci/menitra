<?php

declare(strict_types=1);

/*
| /admin platform management (launch plan Faz 8): tenants, users, audit log,
| impersonation.
*/

return [

    'fields' => [
        'created_at' => 'Oluşturulma',
        'is_active' => 'Aktif',
    ],

    'notifications' => [
        'saved' => 'Kaydedildi.',
        'deleted' => 'Silindi.',
        'restored' => 'Geri alındı.',
    ],

    'tenants' => [
        'singular' => 'İşletme',
        'plural' => 'İşletmeler',
        'deleted' => 'Silindi',
        'no_owner' => 'Sahip yok',
        'no_subscription' => 'Abonelik yok',
        'members' => 'Üyeler',
        'locations' => 'Şubeler',
        'subscription_events' => 'Abonelik geçmişi',
        'sections' => [
            'general' => 'Genel',
            'subscription' => 'Abonelik',
            'subscription_help' => 'Salt okunur. Abonelik değişiklikleri ödeme altyapısıyla gelecek.',
        ],
        'fields' => [
            'name' => 'Ad',
            'owner' => 'Sahip',
            'status' => 'Durum',
            'plan' => 'Paket',
            'subscription' => 'Abonelik',
            'slug' => 'Menü adresi',
            'slug_help' => 'Küçük harf, rakam ve tek tire; 3–40 karakter.',
            'menu_url' => 'Menü linki',
            'default_locale' => 'Varsayılan dil',
            'theme' => 'Tema',
            'deleted_at' => 'Silinme',
            'access_until' => 'Menü açık kalma tarihi',
            'trial_ends_at' => 'Deneme bitişi',
            'period_ends_at' => 'Dönem bitişi',
            'reason' => 'Neden (isteğe bağlı)',
            'reason_help' => 'Sadece denetim kaydına yazılır, işletmeye gösterilmez.',
            'occurred_at' => 'Tarih',
            'event' => 'Olay',
            'from_status' => 'Önceki durum',
            'to_status' => 'Yeni durum',
        ],
        'actions' => [
            'change_slug' => 'Menü adresini değiştir',
            'suspend' => 'Askıya al',
            'unsuspend' => 'Askıyı kaldır',
            'delete' => 'Sil',
            'restore' => 'Geri al',
        ],
        'confirm' => [
            'change_slug' => 'Menü adresi ve /app adresi hemen değişir. Eski menü adresleri yeni adrese kalıcı olarak (301) yönlenir ve başka bir işletme tarafından alınamaz. QR kodları etkilenmez.',
            'suspend' => 'Public menü kapanır ve üyeler /app\'e giremez. Veriler korunur.',
            'delete' => 'İşletme silinmiş olarak işaretlenir: menü kapanır, üyeler erişimini kaybeder. Veriler silinmez ve işletme geri alınabilir.',
            'restore' => 'İşletme, menüsü ve üyelerin erişimi silinmeden önceki durumuna döner.',
        ],
        'errors' => [
            'slug_taken' => 'Bu menü adresi kullanımda veya başka bir işletmeye ayrılmış.',
            'slug_unchanged' => 'Yeni menü adresi mevcut adresle aynı.',
        ],
    ],

    'subscription_events' => [
        'trial_started' => 'Deneme başladı',
        'activated' => 'Etkinleştirildi',
        'renewed' => 'Yenilendi',
        'payment_failed' => 'Ödeme başarısız',
        'cancel_requested' => 'İptal istendi',
        'cancel_reverted' => 'İptal geri alındı',
        'grace_started' => 'Ek süre başladı',
        'expired' => 'Süresi doldu',
        'plan_changed' => 'Paket değişti',
        'reactivated' => 'Yeniden etkinleştirildi',
        'complimentary_started' => 'Ücretsiz abonelik başladı',
    ],

    'users' => [
        'singular' => 'Kullanıcı',
        'plural' => 'Kullanıcılar',
        'active' => 'Aktif',
        'deactivated' => 'Pasif',
        'not_verified' => 'Doğrulanmadı',
        'no_platform_role' => 'Platform rolü yok',
        'memberships' => 'İşletme üyelikleri',
        'sections' => [
            'account' => 'Hesap',
        ],
        'fields' => [
            'name' => 'Ad',
            'email' => 'E-posta',
            'platform_role' => 'Platform rolü',
            'role' => 'Rol',
            'branches' => 'Şubeler',
            'joined' => 'Katılma',
            'memberships' => 'Aktif üyelik',
            'verified' => 'E-posta doğrulandı',
            'verified_at' => 'E-posta doğrulama',
            'two_factor' => 'İki adımlı doğrulama',
            'status' => 'Durum',
            'deactivated_at' => 'Pasifleştirilme',
        ],
        'actions' => [
            'create_platform_user' => 'Platform kullanıcısı ekle',
            'impersonate' => 'Kullanıcı adına görüntüle',
            'resend_verification' => 'Doğrulama mailini tekrar gönder',
            'change_platform_role' => 'Platform rolünü değiştir',
            'deactivate' => 'Pasifleştir',
            'reactivate' => 'Yeniden aktifleştir',
        ],
        'confirm' => [
            'create_platform_user' => 'Kullanıcıya şifre belirleme linki e-postayla gönderilir. İlk /admin girişinde iki adımlı doğrulamayı kurması gerekir.',
            'impersonate' => 'İşletme panelini bu kullanıcının yetkileriyle görürsün. Oturum :minutes dakika sonra kendiliğinden biter; başlangıç ve bitiş denetim kaydına yazılır. Bu sırada şifre, e-posta ve iki adımlı doğrulama değiştirilemez, /admin açılmaz.',
            'change_platform_role' => 'Platform rolü /admin erişimi verir. İşletme üyelikleri değişmez.',
            'deactivate' => 'Kullanıcı hiçbir panele giremez ve açık oturumu bir sonraki istekte kapanır. Üyelikleri ve verileri korunur.',
        ],
        'notifications' => [
            'platform_user_created' => 'Platform kullanıcısı oluşturuldu, şifre belirleme linki gönderildi.',
            'verification_sent' => 'Doğrulama maili kuyruğa alındı.',
        ],
        'errors' => [
            'resend_throttled' => 'Doğrulama maili az önce gönderildi. Lütfen bir dakika sonra tekrar deneyin.',
        ],
    ],

    'impersonation' => [
        'banner' => ':name (:email) adına görüntülüyorsun.',
        'banner_expires' => ':time\'de otomatik biter',
        'leave' => 'Çık',
        'blocked' => 'Kullanıcı adına görüntülerken bu işlem yapılamaz.',
        'errors' => [
            'no_membership' => 'Bu kullanıcının aktif bir işletme üyeliği yok.',
        ],
    ],

    'audit' => [
        'singular' => 'Denetim kaydı',
        'plural' => 'Denetim kaydı',
        'fields' => [
            'created_at' => 'Zaman',
            'actor' => 'Yapan',
            'action' => 'İşlem',
            'subject' => 'Hedef',
            'tenant' => 'İşletme',
            'meta' => 'Ayrıntı',
            'ip' => 'IP',
        ],
        'subjects' => [
            'tenant' => 'İşletme',
            'user' => 'Kullanıcı',
        ],
        'actions' => [
            'tenant_suspended' => 'İşletme askıya alındı',
            'tenant_unsuspended' => 'İşletme askısı kaldırıldı',
            'tenant_slug_changed' => 'Menü adresi değişti',
            'tenant_deleted' => 'İşletme silindi',
            'tenant_restored' => 'İşletme geri alındı',
            'user_platform_user_created' => 'Platform kullanıcısı oluşturuldu',
            'user_platform_role_changed' => 'Platform rolü değişti',
            'user_verification_resent' => 'Doğrulama maili gönderildi',
            'user_deactivated' => 'Kullanıcı pasifleştirildi',
            'user_reactivated' => 'Kullanıcı aktifleştirildi',
            'impersonation_started' => 'Kullanıcı adına görüntüleme başladı',
            'impersonation_ended' => 'Kullanıcı adına görüntüleme bitti',
        ],
    ],

];
