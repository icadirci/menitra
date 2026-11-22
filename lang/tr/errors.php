<?php

declare(strict_types=1);

/*
| HTTP error pages (resources/views/errors). Never mention a business: the
| same texts are shown on tenant menu hosts.
*/

return [
    'code' => 'Hata :code',
    'home' => 'Ana sayfaya dön',
    'platform_home' => 'Menitra’yı keşfedin',

    '403' => [
        'title' => 'Bu sayfaya erişiminiz yok',
        'body' => 'Bu sayfayı görüntüleme yetkiniz bulunmuyor. Doğru hesapla giriş yaptığınızdan emin olun.',
    ],
    '404' => [
        'title' => 'Sayfa bulunamadı',
        'body' => 'Aradığınız sayfa taşınmış, kaldırılmış ya da hiç var olmamış olabilir.',
    ],
    '419' => [
        'title' => 'Oturumunuzun süresi doldu',
        'body' => 'Güvenliğiniz için sayfanın süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.',
    ],
    '429' => [
        'title' => 'Çok fazla istek',
        'body' => 'Kısa sürede çok fazla istek gönderdiniz. Lütfen biraz bekleyip tekrar deneyin.',
    ],
    '500' => [
        'title' => 'Bir şeyler ters gitti',
        'body' => 'Beklenmeyen bir hata oluştu. Lütfen biraz sonra tekrar deneyin.',
    ],
    '503' => [
        'title' => 'Kısa bir bakım yapıyoruz',
        'body' => 'Menitra şu anda bakımda. Birkaç dakika içinde tekrar hizmetinizde olacağız.',
    ],
    'client' => [
        'title' => 'İstek işlenemedi',
        'body' => 'İsteğiniz işlenemedi. Lütfen adresi kontrol edip tekrar deneyin.',
    ],
    'server' => [
        'title' => 'Bir şeyler ters gitti',
        'body' => 'Beklenmeyen bir hata oluştu. Lütfen biraz sonra tekrar deneyin.',
    ],
];
