<?php

declare(strict_types=1);

return [

    'checks' => [
        'failed_jobs' => 'Başarısız kuyruk işleri',
        'disk' => 'Disk kullanımı',
        'backup' => 'Son yedek',
    ],

    'table' => [
        'check' => 'Kontrol',
        'status' => 'Durum',
    ],

    'status' => [
        'ok' => 'OK',
        'disabled' => 'Kapalı',
    ],

    'problems' => [
        'failed_jobs' => 'Son uyarıdan bu yana :count kuyruk işi başarısız oldu (php artisan queue:failed).',
        'disk' => 'Disk %:percent dolu, :free GB boş alan kaldı.',
        'disk_unreadable' => 'Disk kullanımı okunamadı.',
        'backup_missing' => 'Hiç yedek dosyası bulunamadı.',
        'backup_old' => 'Son yedek :hours saat önce alınmış.',
    ],

    'output' => [
        'cooldown' => 'Bu sorunlar için yakın zamanda uyarı gönderildi.',
        'no_recipient' => 'Uyarı e-postası tanımlı değil (OPS_ALERT_EMAIL veya site ayarları).',
        'mail_failed' => 'Uyarı e-postası gönderilemedi; bir sonraki çalışmada tekrar denenecek.',
        'sent' => 'Uyarı :email adresine gönderildi.',
    ],

    'mail' => [
        'subject' => '[:app · :env] :count sağlık kontrolü sorunu',
        'heading' => 'Sağlık kontrolü sorunları',
        'footer' => 'Aynı kontrol için en fazla :minutes dakikada bir uyarı gönderilir.',
    ],

];
