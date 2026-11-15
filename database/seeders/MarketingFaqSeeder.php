<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FaqPlacement;
use App\Models\Faq;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Starter FAQ for the marketing home page. Runs only while the faqs table is
 * empty, so it never overwrites or duplicates content edited in /admin.
 * Answers avoid plan-specific numbers (plans are managed in /admin).
 */
class MarketingFaqSeeder extends Seeder
{
    /**
     * @var list<array<string, array{question: string, answer: string}>>
     */
    private const array ENTRIES = [
        [
            'tr' => ['question' => 'Misafirlerimin uygulama indirmesi gerekiyor mu?', 'answer' => 'Hayır. Telefon kamerasıyla QR kodu okuttuklarında menünüz tarayıcıda açılır; uygulama ya da üyelik gerekmez.'],
            'en' => ['question' => 'Do my guests need to download an app?', 'answer' => 'No. They point their phone camera at the QR code and your menu opens in the browser — no app, no sign-up.'],
        ],
        [
            'tr' => ['question' => 'Fiyatları ve ürünleri istediğim zaman değiştirebilir miyim?', 'answer' => 'Evet. Kaydettiğiniz anda değişiklik yayına girer; istediğiniz kadar sık güncelleyebilirsiniz.'],
            'en' => ['question' => 'Can I change prices and dishes whenever I want?', 'answer' => 'Yes. Changes go live the moment you save them, as often as you like.'],
        ],
        [
            'tr' => ['question' => 'Menüyü düzenlersem QR kodum değişir mi?', 'answer' => 'Hayır. QR kodunuz her zaman aynı menüye yönlenir; bir kez basarsınız ve kullanmaya devam edersiniz.'],
            'en' => ['question' => 'Will my QR code change if I edit the menu?', 'answer' => 'Never. Your QR code always points to the same menu, so you print it once and keep it.'],
        ],
        [
            'tr' => ['question' => 'Menümü birden fazla dilde sunabilir miyim?', 'answer' => 'Evet. Türkçe ve İngilizce menüleri tek yerden yönetirsiniz; misafirleriniz dili tek dokunuşla değiştirir. Dil sayısı paketinize göre belirlenir.'],
            'en' => ['question' => 'Can I offer the menu in more than one language?', 'answer' => 'Yes. You manage Turkish and English menus from one place, and guests switch with one tap. The number of languages depends on your plan.'],
        ],
        [
            'tr' => ['question' => 'İstediğim zaman iptal edebilir miyim?', 'answer' => 'Evet. Aboneliğinizi dilediğiniz zaman iptal edebilirsiniz; verileriniz silinmez.'],
            'en' => ['question' => 'Can I cancel any time?', 'answer' => 'Yes. You can cancel your subscription whenever you like; your data is never deleted.'],
        ],
    ];

    public function run(): void
    {
        if (Faq::query()->exists()) {
            return;
        }

        DB::transaction(function (): void {
            foreach (self::ENTRIES as $order => $translations) {
                $faq = Faq::query()->create(['placement' => FaqPlacement::Home, 'is_published' => true, 'sort_order' => $order]);

                foreach ($translations as $locale => $fields) {
                    $faq->translations()->create(['locale' => $locale, ...$fields]);
                }
            }
        });
    }
}
