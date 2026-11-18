<?php

declare(strict_types=1);

return [

    'payment_statuses' => [
        'pending' => 'Bekliyor',
        'succeeded' => 'Ödendi',
        'failed' => 'Başarısız',
        'expired' => 'Tamamlanmadı',
    ],

    'payment_kinds' => [
        'activation' => 'Aktivasyon',
        'renewal' => 'Yenileme',
        'resubscription' => 'Yeniden abonelik',
        'upgrade' => 'Paket yükseltme',
    ],

    'profile_types' => [
        'individual' => 'Bireysel / Şahıs şirketi',
        'corporate' => 'Kurumsal (tüzel kişi)',
    ],

    'validation' => [
        'tax_id_individual' => 'Geçerli bir T.C. kimlik numarası girin (11 hane).',
        'tax_id_corporate' => 'Geçerli bir vergi kimlik numarası girin (10 hane).',
    ],

    'errors' => [
        'not_allowed' => 'Şu anda ödeme yapılamıyor.',
        'renewal_not_open' => 'Aboneliğiniz aktif. Bir sonraki dönemin ödemesini :date tarihinden itibaren yapabilirsiniz.',
        'plan_not_allowed' => 'Bu paket için şu anda ödeme yapılamıyor. Paket değişikliği ücretli dönem bitince yapılabilir.',
        'billing_profile_missing' => 'Ödemeden önce fatura bilgilerinizi girin.',
        'too_many_attempts' => 'Çok fazla ödeme denemesi yapıldı. Lütfen bir süre sonra tekrar deneyin.',
        'gateway_unavailable' => 'Ödeme sayfası şu anda açılamadı. Kartınızdan çekim yapılmadı; lütfen birkaç dakika sonra tekrar deneyin.',
        'no_price' => 'Bu paket için seçilen dönemde fiyat tanımlı değil.',
    ],

    'actions' => [
        'pay' => 'Öde ve devam et',
        'renew' => 'Aboneliği yenile',
        'upgrade' => 'Paketi yükselt',
        'billing_details' => 'Fatura bilgileri',
        'back_to_subscription' => 'Aboneliğe dön',
    ],

    'subscription_note' => 'Ödemeler iyzico güvencesiyle 3D Secure ile alınır; kart bilgileriniz Restano\'da saklanmaz. Her ödeme bir dönemi (aylık veya yıllık) kapsar ve otomatik çekim yapılmaz; dönem bitmeden size hatırlatma e-postası göndeririz.',

    'checkout' => [
        'title' => 'Ödeme',
        'title_upgrade' => 'Paketi yükselt',
        'subheading' => 'Paketinizi ve ödeme döneminizi seçin; ödemeyi iyzico güvencesiyle tamamlayın.',
        'active' => [
            'title' => 'Aktif aboneliğiniz: :plan · :interval · :date tarihine kadar',
            'upgrade_help' => 'Üst pakete geçerseniz yeni paketiniz ödemeden hemen sonra açılır; yalnızca kalan günler için fark ödersiniz.',
            'renewal_open' => 'Mevcut paketinizi şimdi yenileyebilirsiniz; yeni dönem mevcut dönemin bitişinden başlar.',
        ],
        'badges' => [
            'current' => 'Mevcut paket',
            'upgrade' => 'Yükseltme',
            'popular' => 'En popüler',
        ],
        'select' => 'Bu paketi seç',
        'selected' => 'Seçildi',
        'per_month' => '/ ay',
        'per_year' => '/ yıl',
        'per_month_equivalent' => 'Aylık ortalama :amount',
        'monthly_only' => 'Bu paket yalnızca aylık ödenebilir',
        'saving' => ':amount tasarruf (%:percent)',
        'interval_yearly_saving' => ':interval · %:percent indirim',
        'yearly_perk_months' => '{1} Yıllık ödemede 12 ay yerine :paid ay ödersiniz: 1 ay bedava.|[2,*] Yıllık ödemede 12 ay yerine :paid ay ödersiniz: :free ay bedava.',
        'yearly_perk_percent' => 'Yıllık ödemede %:percent indirim kazanırsınız.',
        'switch_yearly' => 'Yıllık öde',
        'switch_yearly_hint' => 'Yıllık öderseniz :amount tasarruf edersiniz (%:percent).',
        'branch_note' => ':count şube dahil · her ek şube aylık +:amount',
        'upgrade_today' => 'Bugün yalnızca :amount ödersiniz (kalan :days gün için fark)',
        'upgrade_interval' => 'Yükseltme mevcut döneminizin sonuna kadar geçerlidir; ödeme döneminiz :interval olarak kalır.',
        'upgrade_line' => ':plan paketine yükseltme (kalan :days gün)',
        'not_open' => [
            'title' => 'Şu anda ödeme yapılamıyor',
        ],
        'sections' => [
            'interval' => 'Ödeme dönemi',
            'plan' => 'Paket',
            'plan_help' => 'İşletmenize uygun paketi seçin. Tüm paketlerde verileriniz korunur, istediğiniz zaman yükseltebilirsiniz.',
            'plan_active' => 'Mevcut paketinizi yenileyebilir ya da üst pakete hemen geçebilirsiniz.',
            'summary' => 'Sipariş özeti',
            'billing' => 'Fatura bilgileri',
        ],
        'fields' => [
            'terms' => 'Ödemenin tek seferlik bir dönem için alındığını ve',
            'terms_link' => 'kullanım koşullarını okuyup kabul ettiğimi onaylıyorum.',
            'terms_required' => 'Devam etmek için onay kutusunu işaretleyin.',
        ],
        'summary' => [
            'base' => 'Paket ücreti',
            'extra' => 'Ek şubeler',
            'extra_value' => ':count şube · :amount',
            'total' => 'Toplam',
            'vat_included' => 'KDV dahil',
            'period' => 'Kapsanan dönem',
            'saving' => 'Yıllık ödemeyle :amount tasarruf ediyorsunuz (%:percent) · aylık ortalama :monthly',
            'upgrade_title' => ':plan paketine yükseltme',
            'upgrade_charge' => 'Yeni paket (kalan :days gün)',
            'upgrade_credit' => 'Mevcut paketten kalan tutar',
            'upgrade_period' => 'Bugünden :date tarihine kadar (mevcut dönem)',
        ],
        'billing' => [
            'add' => 'Fatura bilgisi ekle',
            'edit' => 'Düzenle',
            'missing' => 'Ödeme yapabilmek için fatura bilgilerinizi ekleyin.',
        ],
        'extra_locations_line' => 'Ek şube (:count adet)',
        'pay' => 'iyzico ile güvenli öde',
        'pay_amount' => ':amount öde',
        'trust' => [
            'secure' => 'iyzico güvencesi ve 3D Secure ile ödeme',
            'card' => 'Kart bilgileriniz Restano\'da saklanmaz',
            'receipt' => 'Makbuzunuz fatura e-postanıza gönderilir',
            'no_auto' => 'Otomatik çekim yok; dönem bitmeden hatırlatırız',
        ],
        'secure_note' => 'Ödeme sayfası iyzico tarafından sunulur. Ödemeden sonra otomatik olarak buraya döneceksiniz.',
    ],

    'result' => [
        'succeeded' => [
            'title' => 'Ödemeniz alındı, teşekkürler!',
            'body' => 'Aboneliğiniz :date tarihine kadar aktif. Makbuzunuzu e-posta ile gönderdik; aşağıdaki ödeme geçmişinden de ulaşabilirsiniz.',
        ],
        'pending' => [
            'title' => 'Ödemeniz kontrol ediliyor',
            'body' => 'Sonuç birkaç dakika içinde netleşecek. Kartınızdan çekim yapıldıysa aboneliğiniz otomatik olarak güncellenir; tekrar ödeme yapmanıza gerek yok.',
        ],
        'failed' => [
            'title' => 'Ödeme tamamlanamadı',
            'body' => 'Kartınızdan çekim yapılmadı. Kart bilgilerinizi kontrol edip tekrar deneyebilir veya farklı bir kart kullanabilirsiniz.',
        ],
        'expired' => [
            'title' => 'Ödeme tamamlanmadı',
            'body' => 'Ödeme sayfasında işlem tamamlanmadı. Dilediğiniz zaman tekrar deneyebilirsiniz.',
        ],
    ],

    'history' => [
        'heading' => 'Ödeme geçmişi',
        'empty' => 'Henüz ödeme yok',
        'receipt' => 'Makbuz',
        'columns' => [
            'date' => 'Tarih',
            'plan' => 'Paket',
            'amount' => 'Tutar',
            'status' => 'Durum',
            'receipt' => 'Makbuz no',
        ],
    ],

    'receipt' => [
        'title' => 'Makbuz :number',
        'print' => 'Yazdır',
        'number' => 'Makbuz no',
        'paid_at' => 'Ödeme tarihi',
        'plan' => 'Paket',
        'period' => 'Dönem',
        'card' => 'Kart',
        'reference' => 'iyzico ödeme no',
        'total' => 'Toplam (KDV dahil)',
        'tax_id' => 'Vergi dairesi / kimlik no',
        'not_invoice' => 'Bu belge ödeme makbuzudur, fatura yerine geçmez. e-Arşiv faturanız fatura e-posta adresinize ayrıca gönderilir.',
        'sections' => [
            'payment' => 'Ödeme',
            'amount' => 'Tutar',
            'billing' => 'Fatura bilgileri',
        ],
    ],

    'profile' => [
        'title' => 'Fatura bilgileri',
        'saved' => 'Fatura bilgileri kaydedildi',
        'sections' => [
            'identity' => 'Fatura türü',
            'identity_help' => 'Faturanız bu bilgilerle düzenlenir. Bilgiler iyzico\'ya ödeme sırasında alıcı bilgisi olarak iletilir.',
            'contact' => 'İletişim ve adres',
        ],
        'fields' => [
            'type' => 'Fatura türü',
            'name' => 'Ad soyad',
            'company_name' => 'Şirket unvanı',
            'tckn' => 'T.C. kimlik no',
            'vkn' => 'Vergi kimlik no',
            'tax_id' => 'Kimlik / vergi no',
            'tax_office' => 'Vergi dairesi',
            'email' => 'Fatura e-postası',
            'email_help' => 'Makbuz ve faturalar bu adrese gönderilir.',
            'phone' => 'Cep telefonu',
            'address' => 'Adres',
            'district' => 'İlçe',
            'city' => 'İl',
            'postal_code' => 'Posta kodu',
        ],
    ],

    'register' => [
        'start' => 'Nasıl başlamak istersiniz?',
        'trial' => ':days gün ücretsiz dene',
        'trial_help' => 'Kart gerekmez. Deneme bitmeden istediğiniz zaman ödeyebilirsiniz.',
        'pay_now' => 'Hemen öde ve başla',
        'pay_now_help' => 'E-postanızı doğruladıktan sonra ödeme sayfasına yönlendirilirsiniz. Deneme süreniz yanmaz: ücretli dönem deneme bitince başlar.',
    ],

    'mail' => [
        'receipt' => [
            'subject' => 'Ödemeniz alındı – makbuz :number',
            'greeting' => 'Merhaba,',
            'intro' => ':business için ödemeniz başarıyla alındı. Teşekkür ederiz!',
            'cta' => 'Makbuzu görüntüle',
        ],
        'reminder' => [
            'greeting' => 'Merhaba,',
            'cta' => 'Şimdi öde',
            'outro' => 'Ödeme iyzico güvencesiyle birkaç dakikada tamamlanır. Menüleriniz ve tüm verileriniz her durumda korunur.',
            'trial_ending' => [
                'subject' => ':business – deneme süreniz bitiyor',
                'body' => '{0} :business için :plan paketinin deneme süresi bugün (:date) bitiyor. Menünüzün kesintisiz yayında kalması için ödemenizi yapın.|{1} :business için :plan paketinin deneme süresi yarın (:date) bitiyor. Menünüzün kesintisiz yayında kalması için ödemenizi yapın.|[2,*] :business için :plan paketinin deneme süresi :count gün sonra (:date) bitiyor. Menünüzün kesintisiz yayında kalması için ödemenizi yapın.',
            ],
            'period_ending' => [
                'subject' => ':business – abonelik döneminiz bitiyor',
                'body' => '{0} :business için :plan aboneliğinizin dönemi bugün (:date) bitiyor. Yenilemek için ödemenizi yapın.|{1} :business için :plan aboneliğinizin dönemi yarın (:date) bitiyor. Yenilemek için ödemenizi yapın.|[2,*] :business için :plan aboneliğinizin dönemi :count gün sonra (:date) bitiyor. Yenilemek için ödemenizi yapın.',
            ],
            'grace_started' => [
                'subject' => ':business – ödemeniz bekleniyor',
                'body' => ':business için :plan aboneliğinizin süresi doldu ve ek süreye girdi. Menünüz :date tarihine kadar yayında kalır; bu tarihe kadar ödeme yapılmazsa menünüz yayından kalkar.',
            ],
        ],
    ],

];
