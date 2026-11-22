<?php

declare(strict_types=1);

/*
 * HUKUKİ İNCELEME GEREKLİ — otomatik çalışmada (2026-11-15) yazılmış genel
 * taslak metinlerdir. Yayına almadan önce bir avukat tarafından KVKK ve
 * ilgili mevzuata göre gözden geçirilmeli; köşeli parantezli yer tutucular
 * ([...]) doldurulmalıdır. İnceleme bitince config marketing.legal_review_pending
 * false yapılır (sayfadaki "taslak" uyarısı kalkar).
 */

return [

    'eyebrow' => 'Yasal',
    'updated' => 'Son güncelleme: :date',
    'draft_title' => 'Taslak metin.',
    'draft_body' => 'Bu metin genel bir taslaktır ve henüz hukuki incelemeden geçmemiştir. Bağlayıcı sürüm yayımlanana kadar sorularınız için bizimle iletişime geçin.',
    'contact_lead' => 'Bu metinle ilgili sorularınız ve talepleriniz için:',
    'contact_link' => 'İletişim sayfası',

    'privacy' => [
        'title' => 'Gizlilik ve Kişisel Verilerin Korunması',
        'description' => 'Menitra\'nın kişisel verileri hangi amaçlarla işlediği, nasıl koruduğu ve 6698 sayılı KVKK kapsamındaki haklarınız.',
        'updated' => '15 Kasım 2026',
        'intro' => 'Bu aydınlatma metni, 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") uyarınca, Menitra platformunu (web sitesi, işletme paneli ve QR menü sayfaları) kullanırken kişisel verilerinizin nasıl işlendiğini açıklar.',
        'sections' => [
            [
                'title' => '1. Veri sorumlusu',
                'paragraphs' => [
                    'Veri sorumlusu, [şirket unvanı] ([adres], [MERSİS / vergi numarası]) olup bu metinde "Menitra" olarak anılır.',
                    'İşletmelerin QR menülerinde yayımladıkları içerik (ürünler, fiyatlar, işletme bilgileri) ilgili işletmenin sorumluluğundadır.',
                ],
            ],
            [
                'title' => '2. İşlenen kişisel veriler',
                'paragraphs' => ['Hizmetin türüne göre aşağıdaki veriler işlenebilir:'],
                'items' => [
                    'Hesap bilgileri: ad soyad, e-posta adresi, şifrenin geri döndürülemez özeti (hash), e-posta doğrulama durumu.',
                    'İşletme bilgileri: işletme adı, şube adresleri, telefon ve iletişim bilgileri, menü içerikleri ve yüklenen görseller.',
                    'Abonelik bilgileri: seçilen plan, deneme ve abonelik tarihleri.',
                    'İletişim formu: adınız, e-posta adresiniz, konu ve mesajınız. Bu mesajlar e-posta ile ekibimize iletilir, veritabanında saklanmaz.',
                    'Teknik kayıtlar: IP adresi, tarayıcı bilgisi, istek zamanı gibi güvenlik ve hata kayıtları.',
                ],
            ],
            [
                'title' => '3. İşleme amaçları ve hukuki sebepler',
                'paragraphs' => ['Kişisel veriler; hesabın oluşturulması ve hizmetin sunulması (KVKK m.5/2-c, sözleşmenin ifası), yasal yükümlülüklerin yerine getirilmesi (m.5/2-ç), hizmet güvenliğinin sağlanması ve kötüye kullanımın önlenmesi (m.5/2-f, meşru menfaat) ve taleplerinize yanıt verilmesi amaçlarıyla işlenir.'],
            ],
            [
                'title' => '4. Çerezler',
                'paragraphs' => [
                    'Web sitesi ve işletme paneli, yalnızca oturumunuzu ve form güvenliğini (CSRF) sağlamak için zorunlu çerezler kullanır. Reklam veya üçüncü taraf takip çerezleri kullanılmaz.',
                    'QR menü sayfaları çerez kullanmaz ve oturum başlatmaz.',
                ],
            ],
            [
                'title' => '5. Aktarım',
                'paragraphs' => ['Veriler; barındırma, e-posta gönderimi ve teknik altyapı hizmeti aldığımız tedarikçilerle, yalnızca hizmetin sunulması için gerekli ölçüde ve KVKK m.8 ve m.9\'a uygun olarak paylaşılabilir. [Tedarikçi listesi ve yurt dışı aktarım bilgisi eklenecek.] Yasal zorunluluk hâlinde yetkili kurumlarla paylaşılabilir.'],
            ],
            [
                'title' => '6. Saklama süresi',
                'paragraphs' => ['Veriler, hizmet ilişkisi süresince ve ilgili mevzuatta öngörülen süreler boyunca saklanır. Aboneliğin sona ermesi işletme verilerinin otomatik olarak silinmesi anlamına gelmez; silme talepleri ayrıca değerlendirilir. [Somut saklama süreleri eklenecek.]'],
            ],
            [
                'title' => '7. Haklarınız',
                'paragraphs' => ['KVKK m.11 uyarınca; verilerinizin işlenip işlenmediğini öğrenme, bilgi talep etme, amacına uygun kullanılıp kullanılmadığını öğrenme, aktarıldığı üçüncü kişileri bilme, eksik veya yanlış işlenmişse düzeltilmesini, şartları oluştuğunda silinmesini veya yok edilmesini isteme, itiraz etme ve zararın giderilmesini talep etme haklarına sahipsiniz. Başvurularınızı iletişim sayfası üzerinden veya [başvuru adresi] adresine iletebilirsiniz.'],
            ],
        ],
    ],

    'terms' => [
        'title' => 'Kullanım Koşulları',
        'description' => 'Menitra QR menü platformunun kullanım koşulları: hesap, deneme ve abonelik, içerik sorumluluğu ve hizmetin sınırları.',
        'updated' => '15 Kasım 2026',
        'intro' => 'Bu koşullar, [şirket unvanı] ("Menitra") tarafından sunulan QR menü platformunun işletmeler tarafından kullanımını düzenler. Hesap oluşturarak bu koşulları kabul etmiş olursunuz.',
        'sections' => [
            [
                'title' => '1. Hizmet',
                'paragraphs' => ['Menitra; işletmelerin dijital menülerini oluşturmasını, QR kodlarla yayımlamasını ve şubelerini, menülerini ve ürün durumlarını yönetmesini sağlayan bir yazılım hizmetidir. Online sipariş, ödeme veya rezervasyon hizmeti sunulmaz.'],
            ],
            [
                'title' => '2. Hesap ve güvenlik',
                'paragraphs' => ['Kayıt sırasında verdiğiniz bilgilerin doğru olması ve hesap bilgilerinizi gizli tutmanız sizin sorumluluğunuzdadır. Hesabınız üzerinden yapılan işlemlerden siz sorumlusunuz.'],
            ],
            [
                'title' => '3. Deneme, abonelik ve ücretler',
                'paragraphs' => [
                    'Yeni işletmeler ücretsiz deneme süresiyle başlar. Deneme veya abonelik süresi bittiğinde kısa bir ek süre tanınır; bu sürenin sonunda yenilenmeyen hesapların menüleri yayından kalkar.',
                    'Menüleriniz, şubeleriniz ve QR kodlarınız abonelik sona erdiğinde otomatik olarak silinmez; aboneliğinizi yenilediğinizde yeniden yayına alınır. Plan fiyatları ve limitleri fiyatlandırma sayfasında yer alır. [Ödeme, fatura ve iade koşulları ödeme altyapısı ile birlikte eklenecek.]',
                ],
            ],
            [
                'title' => '4. İçerik sorumluluğu',
                'paragraphs' => ['Menüde yayımlanan ürün adları, açıklamalar, fiyatlar, alerjen ve içerik bilgileri ile görseller işletmenin sorumluluğundadır. İşletme, bu bilgilerin doğru, güncel ve mevzuata (fiyat etiketi, gıda ve tüketici mevzuatı dâhil) uygun olmasını sağlar ve üçüncü kişilerin haklarını ihlal eden içerik yüklemez.'],
            ],
            [
                'title' => '5. Kabul edilemez kullanım',
                'paragraphs' => ['Aşağıdaki kullanımlar yasaktır:'],
                'items' => [
                    'Hizmeti hukuka aykırı amaçlarla veya yanıltıcı içerikle kullanmak,',
                    'Diğer işletmelerin verilerine erişmeye çalışmak, güvenlik önlemlerini aşmaya çalışmak,',
                    'Hizmetin işleyişini bozacak otomatik veya yoğun istekler göndermek.',
                ],
            ],
            [
                'title' => '6. Hizmetin sürekliliği',
                'paragraphs' => ['Hizmetin kesintisiz ve hatasız olması için makul çabayı gösteririz; ancak bakım, güncelleme veya kontrolümüz dışındaki nedenlerle kesintiler yaşanabilir. Koşulları ihlal eden hesaplar askıya alınabilir; askıya alma verileri silmez.'],
            ],
            [
                'title' => '7. Sorumluluğun sınırı',
                'paragraphs' => ['Yürürlükteki mevzuatın izin verdiği ölçüde, dolaylı zararlardan ve kâr kaybından sorumlu değiliz. [Sorumluluk sınırı ve tutarı hukuki incelemeyle belirlenecek.]'],
            ],
            [
                'title' => '8. Değişiklikler ve uygulanacak hukuk',
                'paragraphs' => ['Bu koşulları güncelleyebiliriz; önemli değişiklikleri kayıtlı e-posta adresinize bildiririz. Bu koşullara Türkiye Cumhuriyeti hukuku uygulanır. [Yetkili mahkeme ve icra daireleri belirtilecek.]'],
            ],
        ],
    ],

];
