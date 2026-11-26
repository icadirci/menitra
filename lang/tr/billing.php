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
        'shop_order' => 'QR stand siparişi',
    ],

    'profile_types' => [
        'individual' => 'Bireysel / Şahıs şirketi',
        'corporate' => 'Kurumsal (tüzel kişi)',
    ],

    'validation' => [
        'tax_id_individual' => 'Geçerli bir T.C. kimlik numarası girin (11 hane).',
        'iban' => 'Geçerli bir TR IBAN girin (TR ile başlayan 26 karakter).',
        'tax_id_corporate' => 'Geçerli bir vergi kimlik numarası girin (10 hane).',
    ],

    'errors' => [
        'shop_closed' => 'Mağaza şu anda kapalı.',
        'shop_cart_empty' => 'Sepetinizde satın alınabilecek ürün yok.',
        'shop_stock' => ':product için en fazla :max adet sipariş verilebilir.',
        'shop_price_changed' => 'Sepetinizdeki fiyatlar güncellendi. Yeni toplamı kontrol edip tekrar deneyin.',
        'not_allowed' => 'Şu anda ödeme yapılamıyor.',
        'prepaid_limit' => 'Aboneliğiniz izin verilen en uzun süreye kadar önceden ödenmiş durumda. Süreyi :date tarihinden itibaren tekrar uzatabilirsiniz.',
        'periods_not_allowed' => 'Bu ödemede en fazla :max yıl seçebilirsiniz.',
        'plan_not_allowed' => 'Bu paket için şu anda ödeme yapılamıyor. Paket değişikliği ücretli dönem bitince yapılabilir.',
        'billing_profile_missing' => 'Ödemeden önce fatura bilgilerinizi girin.',
        'too_many_attempts' => 'Çok fazla ödeme denemesi yapıldı. Lütfen bir süre sonra tekrar deneyin.',
        'gateway_rejected' => 'Ödeme sayfası açılamadı: iyzico gönderilen bilgileri kabul etmedi. Kartınızdan çekim yapılmadı. Fatura bilgilerinizi (e-posta, telefon, T.C. kimlik / vergi no, adres) kontrol edip tekrar deneyin.',
        'gateway_unavailable' => 'Ödeme sayfası şu anda açılamadı. Kartınızdan çekim yapılmadı; lütfen birkaç dakika sonra tekrar deneyin.',
        'method_unavailable' => 'Seçilen ödeme yöntemi şu anda kullanılamıyor. Lütfen başka bir yöntem seçin veya daha sonra tekrar deneyin.',
        'transfer_open' => 'Bekleyen bir havale/EFT ödemeniz var. Yeni bir ödeme başlatmadan önce onu tamamlayın, onaylanmasını bekleyin veya iptal edin.',
        'no_price' => 'Bu paket için seçilen dönemde fiyat tanımlı değil.',
    ],

    'actions' => [
        'pay' => 'Öde ve devam et',
        'extend' => 'Süreyi uzat',
        'choose_paid_plan' => 'Ücretli pakete geç',
        'upgrade' => 'Paketi yükselt',
        'billing_details' => 'Fatura bilgileri',
        'back_to_subscription' => 'Aboneliğe dön',
    ],

    'subscription_note' => 'Kartla ödemeler iyzico güvencesiyle 3D Secure ile alınır ve kart bilgileriniz Menitra\'da saklanmaz; havale/EFT ödemeleri ekibimizce onaylanır. Her ödeme bir ayı ya da bir veya birkaç yılı kapsar, süreyi istediğiniz zaman uzatabilirsiniz ve otomatik çekim yapılmaz; süre bitmeden size hatırlatma e-postası göndeririz.',

    'checkout' => [
        'title' => 'Ödeme',
        'title_active' => 'Süreyi uzat veya paketi yükselt',
        'title_upgrade' => 'Paketi yükselt',
        'subheading' => 'Paketinizi ve ödeme döneminizi seçin, ardından ödemeyi tamamlayın.',
        'active' => [
            'title' => 'Aktif aboneliğiniz: :plan · :interval · :date tarihine kadar',
            'upgrade_help' => 'Üst pakete geçerseniz yeni paketiniz ödemeden hemen sonra açılır; yalnızca önceden ödenmiş kalan süreniz için fark ödersiniz.',
            'extend_open' => 'Paketinizin süresini istediğiniz zaman uzatabilirsiniz; yeni süre mevcut bitiş tarihinin üzerine eklenir, kalan günleriniz kaybolmaz.',
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
            'plan_active' => 'Mevcut paketinizin süresini uzatabilir ya da üst pakete hemen geçebilirsiniz.',
            'summary' => 'Sipariş özeti',
            'billing' => 'Fatura bilgileri',
        ],
        'fields' => [
            'terms' => 'Ödemenin tek seferlik bir dönem için alındığını ve',
            'terms_link' => 'kullanım koşullarını okuyup kabul ettiğimi onaylıyorum.',
            'years' => 'Kaç yıllık ödemek istiyorsunuz?',
            'terms_required' => 'Devam etmek için onay kutusunu işaretleyin.',
        ],
        'summary' => [
            'base' => 'Paket ücreti',
            'base_years' => 'Paket ücreti (:period)',
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
        'extra_locations_line_periods' => 'Ek şube (:count adet, :period)',
        'years_extend_help' => 'Seçtiğiniz süre mevcut bitiş tarihiniz olan :date tarihinin üzerine eklenir.',
        'pay' => 'iyzico ile güvenli öde',
        'pay_amount' => ':amount öde',
        'trust' => [
            'secure' => 'iyzico güvencesi ve 3D Secure ile ödeme',
            'card' => 'Kart bilgileriniz Menitra\'da saklanmaz',
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
        'heading' => 'Ödeme makbuzu',
        'line_description' => 'Açıklama',
        'line_amount' => 'Tutar',
        'title' => 'Makbuz :number',
        'print' => 'Yazdır',
        'number' => 'Makbuz no',
        'paid_at' => 'Ödeme tarihi',
        'plan' => 'Paket',
        'period' => 'Dönem',
        'card' => 'Ödeme yöntemi',
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

    'methods' => [
        'label' => 'Ödeme yöntemi',
        'card' => 'Kredi / banka kartı',
        'bank_transfer' => 'Havale / EFT',
    ],

    'bank_transfer' => [
        'title' => 'Havale / EFT ile ödeme',
        'pay_amount' => ':amount havale/EFT ile öde',
        'method_help' => 'Sonraki adımda hesap bilgilerimizi ve açıklamaya yazmanız gereken ödeme kodunu göreceksiniz. Ödemeniz onaylandığında aboneliğiniz / siparişiniz güncellenir.',
        'trust' => 'Ödemeniz ekibimiz tarafından kontrol edilip onaylanır',
        'amount' => 'Tutar',
        'reference' => 'Ödeme kodu',
        'reference_help' => 'Bu kodu havale/EFT açıklamasına yazın.',
        'purpose' => 'Ödeme konusu',
        'instructions' => 'Açıklama',
        'sections' => [
            'payment' => 'Ödeme bilgileri',
            'account' => 'Hesap bilgileri',
        ],
        'states' => [
            'awaiting' => [
                'title' => 'Ödemeniz bekleniyor',
                'body' => 'Tutarı aşağıdaki hesaba gönderin ve açıklamaya :reference kodunu yazın. Ödemeyi yaptıktan sonra "Ödemeyi yaptım" düğmesine tıklayın.',
            ],
            'notified' => [
                'title' => 'Ödeme bildiriminiz alındı',
                'body' => ':reference kodlu ödemeniz kontrol ediliyor. Tutar hesabımıza ulaştığında onaylanır ve size e-posta göndeririz.',
            ],
            'succeeded' => [
                'title' => 'Ödemeniz onaylandı, teşekkürler!',
                'body' => ':reference kodlu havale/EFT ödemeniz onaylandı.',
            ],
            'rejected' => [
                'title' => 'Ödemeniz onaylanmadı',
                'body' => ':reference kodlu ödeme onaylanmadı. Gerekçe: :reason. Sorularınız için bizimle iletişime geçin.',
            ],
            'cancelled' => [
                'title' => 'Ödeme iptal edildi',
                'body' => ':reference kodlu havale/EFT ödemesi iptal edildi. Dilediğiniz zaman yeni bir ödeme başlatabilirsiniz.',
            ],
        ],
        'open' => [
            'title' => 'Bekleyen bir havale/EFT ödemeniz var',
            'body' => ':reference kodlu ödeme tamamlanana, onaylanana veya iptal edilene kadar yeni bir abonelik ödemesi başlatılamaz.',
            'body_shop' => ':reference kodlu sipariş ödemesi tamamlanana, onaylanana veya iptal edilene kadar yeni bir sipariş verilemez.',
            'action' => 'Havale bilgilerini göster',
        ],
        'actions' => [
            'notify' => 'Ödemeyi yaptım',
            'notify_confirm' => ':amount tutarını açıklamaya :reference yazarak gönderdiğinizi onaylıyor musunuz? Ekibimiz ödemeyi kontrol edip onaylayacak.',
            'notified' => 'Bildiriminiz alındı. Ödemeniz onaylandığında size haber vereceğiz.',
            'cancel' => 'Ödemeyi iptal et',
            'cancel_confirm' => 'Bu havale/EFT ödemesi iptal edilecek. Henüz para göndermediyseniz devam edin.',
            'cancelled' => 'Ödeme iptal edildi.',
        ],
        'errors' => [
            'not_open' => 'Bu ödeme artık beklemede değil.',
        ],
        'statuses' => [
            'awaiting' => 'Ödeme bekleniyor',
            'notified' => 'Onay bekliyor',
            'rejected' => 'Reddedildi',
            'cancelled' => 'İptal edildi',
        ],
        'mail' => [
            'subject' => 'Havale/EFT bildirimi: :reference',
            'intro' => ':business havale/EFT ile ödeme yaptığını bildirdi.',
            'check' => 'Banka hesabını kontrol edip ödemeyi yönetim panelinden onaylayın veya reddedin.',
        ],
        'admin' => [
            'singular' => 'Havale/EFT',
            'plural' => 'Havale/EFT ödemeleri',
            'purpose' => 'Konu',
            'notified_at' => 'Bildirildi',
            'shop_order' => 'Stand siparişi :number',
            'upgrade' => ':plan paketine yükseltme',
            'approve' => 'Onayla',
            'approve_heading' => ':reference ödemesini onayla',
            'approve_confirm' => ':amount tutarının bu ödeme koduyla hesaba geçtiğini kontrol ettiniz mi? Onay, ödemeyi kart ödemesi gibi uygular (abonelik başlar / uzar ya da sipariş ödenmiş olur) ve geri alınamaz.',
            'approved' => 'Ödeme onaylandı',
            'reject' => 'Reddet',
            'reject_confirm' => 'Ödeme uygulanmaz. İşletme sahibi gerekçeyi ödeme sayfasında görür.',
            'reject_reason' => 'Gerekçe',
            'reject_reason_help' => 'İşletme sahibine gösterilir.',
            'rejected' => 'Ödeme reddedildi',
        ],
    ],

    'settings' => [
        'title' => 'Ödeme ayarları',
        'subheading' => 'İşletmelerin hangi yöntemlerle ödeme yapabileceğini ve havale/EFT için hesap bilgilerini belirleyin.',
        'sections' => [
            'methods' => 'Ödeme yöntemleri',
            'methods_help' => 'Kapalı bir yöntem ödeme sayfalarında gösterilmez ve sunucuda da reddedilir. İkisi de kapalıysa yeni ödeme alınamaz.',
            'bank' => 'Havale / EFT hesabı',
            'bank_help' => 'Bu bilgiler ve açıklama metni, havale/EFT seçen işletme sahibine ödeme sayfasında gösterilir.',
        ],
        'fields' => [
            'card_enabled' => 'Kartla ödeme (iyzico)',
            'card_enabled_help' => 'iyzico hesabı ve anahtarları hazır değilse kapalı tutun.',
            'bank_transfer_enabled' => 'Havale / EFT',
            'bank_transfer_enabled_help' => 'İşletme ödemeyi yapıp "Ödemeyi yaptım" der; ödeme "Havale/EFT ödemeleri" sayfasında elle onaylanır.',
            'bank_account_holder' => 'Hesap sahibi',
            'bank_name' => 'Banka',
            'bank_iban' => 'IBAN',
            'notification_email' => 'Bildirim e-postası',
            'notification_email_help' => '"Ödemeyi yaptım" bildirimleri bu adrese gider. Boşsa site ayarlarındaki iletişim e-postası kullanılır.',
            'instructions' => 'Açıklama metni',
            'instructions_help' => 'Ödeme sayfasında hesap bilgilerinin altında gösterilir. Kullanılabilir alanlar: :placeholders (ödeme kodu, tutar, işletme adı).',
            'instructions_placeholder' => 'Havale/EFT açıklamasına yalnızca {reference} yazın. Ödemeniz genellikle 1 iş günü içinde onaylanır.',
        ],
        'errors' => [
            'bank_account_required' => 'Havale/EFT açıkken hesap sahibi ve IBAN zorunludur.',
        ],
    ],

];
