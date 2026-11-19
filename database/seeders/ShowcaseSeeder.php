<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ExtraSelectionType;
use App\Enums\MenuTheme;
use App\Enums\MenuType;
use App\Enums\PlanFeature;
use App\Models\LocationItemState;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Subscription\FeatureGate;
use App\Services\Subscription\SubscriptionService;
use App\Services\Themes\ThemeService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Database\Seeders\Concerns\SeedsMenuContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Theme showcase businesses (/temalar, config marketing.showcase), one per
 * built theme. Safe for production:
 *
 *   php artisan db:seed --class=ShowcaseSeeder --force
 *
 * - No memberships and no users: nobody can sign in to these businesses.
 * - A complimentary subscription on the non-public "demo" plan
 *   (SubscriptionService::startComplimentary(), period until ACCESS_UNTIL),
 *   so the lifecycle never moves them to grace. Not a payment flow.
 * - Idempotent: an existing business (also soft-deleted) is left untouched,
 *   and an existing "demo" plan keeps the limits edited in /admin (it must
 *   keep premium_themes, or saving the showcase themes fails).
 *
 * Venue names come from the design files' sample content. DemoSeeder runs
 * this seeder locally and adds the Kahve Konağı team on top.
 */
class ShowcaseSeeder extends Seeder
{
    use SeedsMenuContent;

    public const string PLAN_CODE = 'demo';

    /** End of the complimentary period (UTC). */
    public const string ACCESS_UNTIL = '2099-12-31 23:59:59';

    public function run(): void
    {
        DB::transaction(function (): void {
            $plan = $this->plan();

            $this->business('kahve-konagi', [
                'tr' => ['name' => 'Kahve Konağı', 'tagline' => 'Közde Türk kahvesi', 'description' => "Közde pişen Türk kahvesi, demlikte çay ve ev yapımı tatlılar. Moda'nın arka sokaklarında, yüz yıllık bir konakta."],
                'en' => ['name' => 'Kahve Konağı', 'tagline' => 'Turkish coffee over embers', 'description' => 'Turkish coffee brewed over embers, steeped tea and homemade desserts — in a century-old mansion in the back streets of Moda.'],
            ], $plan, null, fn () => $this->kahveKonagi());

            foreach ($this->showcaseData() as $slug => $venue) {
                $this->business($slug, $venue['brand'], $plan, $venue['theme'], fn () => $this->venue($venue));
            }
        });

        app(TenantContext::class)->clear();
    }

    /**
     * Non-public plan with room for the showcase (not listed on the pricing
     * page). Created once; an existing plan is not overwritten.
     */
    private function plan(): Plan
    {
        $plan = Plan::query()->where('code', self::PLAN_CODE)->first();

        if ($plan !== null) {
            return $plan;
        }

        $plan = new Plan;
        $plan->forceFill(['code' => self::PLAN_CODE, 'is_active' => true, 'is_public' => false, 'is_featured' => false, 'trial_days' => 365, 'sort_order' => 99])->save();

        foreach (['tr' => 'Demo', 'en' => 'Demo'] as $locale => $name) {
            $plan->translations()->create(['locale' => $locale, 'name' => $name]);
        }

        $limits = [
            'max_locations' => 5, 'max_menus' => 10, 'max_items' => 500, 'max_languages' => 3, 'max_qr_codes' => 50, 'max_tables' => 30, 'max_members' => 10,
            'premium_themes' => 1, 'custom_domain' => 0, 'remove_branding' => 0, 'qr_analytics' => 0, 'waiter_call' => 1,
        ];

        foreach ($limits as $feature => $value) {
            $plan->limits()->create(['feature' => PlanFeature::from($feature), 'limit_value' => $value]);
        }

        return $plan;
    }

    /**
     * Creates one business (tr + en, complimentary subscription, theme) and
     * its content inside its own TenantContext, unless the slug exists.
     *
     * @param  array<string, array<string, string>>  $brand
     * @param  Closure(): void  $content
     */
    private function business(string $slug, array $brand, Plan $plan, ?MenuTheme $theme, Closure $content): void
    {
        if (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $this->command?->warn("{$slug} already exists; skipped.");

            return;
        }

        $tenant = $this->tenant($slug, $brand);
        $context = app(TenantContext::class);
        $context->set($tenant);

        try {
            foreach (['tr', 'en'] as $order => $locale) {
                $tenant->locales()->create(['locale' => $locale, 'sort_order' => $order]);
            }

            app(SubscriptionService::class)->startComplimentary($tenant, $plan, CarbonImmutable::parse(self::ACCESS_UNTIL, 'UTC'), 'showcase');
            // The service flushes after commit; the seeder's outer transaction is still open.
            app(FeatureGate::class)->flush();

            if ($theme !== null) {
                app(ThemeService::class)->save($tenant, $theme, $theme->defaults());
            }

            $content();
        } finally {
            $context->clear();
        }
    }

    /**
     * The classic showcase and the richest demo: two branches, two menus,
     * variants, extras, allergens, sold-out states, hours and QR codes.
     */
    private function kahveKonagi(): void
    {
        // Branches
        $moda = $this->location('moda', true, [
            'phone' => '+90 216 555 01 18', 'whatsapp' => '+90 555 555 01 18', 'email' => 'moda@kahvekonagi.test',
            'address_line' => 'Moda Cd. No:118', 'district' => 'Kadıköy', 'city' => 'İstanbul', 'postal_code' => '34710',
            'latitude' => '40.983900', 'longitude' => '29.025400',
        ], ['tr' => 'Moda', 'en' => 'Moda'], ['tr' => 'Deniz kenarında, bahçeli konak.', 'en' => 'A mansion with a garden by the sea.']);
        $cihangir = $this->location('cihangir', false, [
            'phone' => '+90 212 555 02 40', 'address_line' => 'Akarsu Yokuşu No:24', 'district' => 'Beyoğlu', 'city' => 'İstanbul',
        ], ['tr' => 'Cihangir', 'en' => 'Cihangir'], ['tr' => 'Yokuşun başında küçük bir kahve evi.', 'en' => 'A small coffee house at the top of the hill.']);

        foreach (range(1, 7) as $day) {
            $moda->openingHours()->create(['day_of_week' => $day, 'opens_at' => '08:00', 'closes_at' => '00:00']);
            $cihangir->openingHours()->create($day <= 5
                ? ['day_of_week' => $day, 'opens_at' => '07:30', 'closes_at' => '22:00']
                : ['day_of_week' => $day, 'opens_at' => '09:00', 'closes_at' => '01:00']);
        }
        $cihangir->specialHours()->create(['date' => CarbonImmutable::now('Europe/Istanbul')->addDays(10)->toDateString(), 'is_closed' => true]);
        $moda->specialHours()->create(['date' => CarbonImmutable::now('Europe/Istanbul')->addDays(10)->toDateString(), 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '18:00']);

        // Extra groups (shared by both menus)
        $sugar = $this->extraGroup(ExtraSelectionType::Single, ['tr' => 'Şeker tercihi', 'en' => 'Sugar'], [
            [['tr' => 'Sade', 'en' => 'No sugar'], 0],
            [['tr' => 'Az şekerli', 'en' => 'A little'], 0],
            [['tr' => 'Orta', 'en' => 'Medium'], 0],
            [['tr' => 'Şekerli', 'en' => 'Sweet'], 0],
        ]);
        $milk = $this->extraGroup(ExtraSelectionType::Single, ['tr' => 'Süt seçimi', 'en' => 'Milk choice'], [
            [['tr' => 'İnek sütü', 'en' => 'Cow\'s milk'], 0],
            [['tr' => 'Yulaf sütü', 'en' => 'Oat milk'], 2500],
            [['tr' => 'Badem sütü', 'en' => 'Almond milk'], 3000],
        ]);
        $addOns = $this->extraGroup(ExtraSelectionType::Multiple, ['tr' => 'Yanına', 'en' => 'On the side'], [
            [['tr' => 'Kaymak', 'en' => 'Clotted cream'], 4000],
            [['tr' => 'Dondurma', 'en' => 'Ice cream'], 5000],
        ], max: 2);

        // Main menu: shared by both branches (owner-only for content edits).
        $main = $this->menu('menu', MenuType::AllDay, ['tr' => 'Ana Menü', 'en' => 'Main menu'], [$moda, $cihangir]);

        $coffee = $this->category($main, ['tr' => 'Kahveler', 'en' => 'Coffee']);
        $this->item($coffee, null, ['tr' => ['Türk Kahvesi', 'Közde pişer; lokum ve su ile servis edilir.'], 'en' => ['Turkish Coffee', 'Brewed over embers; served with Turkish delight and water.']],
            featured: true, popular: true, diet: ['vegan'], prep: 8, extras: [$sugar],
            variants: [[['tr' => 'Tek', 'en' => 'Single'], 15000, true], [['tr' => 'Duble', 'en' => 'Double'], 21000, false]]);
        $this->item($coffee, 16000, ['tr' => ['Menengiç Kahvesi', 'Kafeinsiz, sütlü; Güneydoğu usulü.'], 'en' => ['Menengiç Coffee', 'Caffeine-free terebinth coffee with milk.']],
            diet: ['vegetarian'], allergens: ['milk'], extras: [$sugar]);
        $this->item($coffee, 16000, ['tr' => ['Dibek Kahvesi', 'Taş dibekte dövülmüş, yumuşak içim.'], 'en' => ['Dibek Coffee', 'Stone-ground for a smooth cup.']],
            diet: ['vegan'], extras: [$sugar]);
        $this->item($coffee, 14000, ['tr' => ['Latte', 'Çift shot espresso, ipeksi süt köpüğü.'], 'en' => ['Latte', 'Double-shot espresso with silky milk.']],
            diet: ['vegetarian'], allergens: ['milk'], extras: [$milk], calories: 190);

        $tea = $this->category($main, ['tr' => 'Çaylar', 'en' => 'Tea']);
        $this->item($tea, null, ['tr' => ['Demleme Çay', 'Rize çayı, ince belli bardakta.'], 'en' => ['Black Tea', 'Rize tea in a tulip glass.']],
            diet: ['vegan'], variants: [[['tr' => 'Küçük', 'en' => 'Small'], 4500, true], [['tr' => 'Büyük', 'en' => 'Large'], 7000, false]]);
        $this->item($tea, 9000, ['tr' => ['Ihlamur', 'Bal ve limon ile, ev yapımı.'], 'en' => ['Linden Tea', 'Homemade, with honey and lemon.']], diet: ['vegetarian']);

        $desserts = $this->category($main, ['tr' => 'Tatlılar', 'en' => 'Desserts']);
        $this->item($desserts, 22000, ['tr' => ['Fıstıklı Baklava', '4 dilim, Antep fıstığı, kaymak ile.'], 'en' => ['Pistachio Baklava', '4 pieces, Antep pistachio, with clotted cream.']],
            featured: true, diet: ['vegetarian'], allergens: ['gluten', 'nuts', 'milk'], extras: [$addOns], calories: 520);
        $sutlac = $this->item($desserts, 15000, ['tr' => ['Fırın Sütlaç', 'Toprak kapta, tarçın ile.'], 'en' => ['Baked Rice Pudding', 'In a clay pot, with cinnamon.']],
            diet: ['vegetarian', 'gluten_free'], allergens: ['milk']);

        $cold = $this->category($main, ['tr' => 'Soğuk içecekler', 'en' => 'Cold drinks']);
        $this->item($cold, 9500, ['tr' => ['Ev Yapımı Limonata', 'Taze nane ile.'], 'en' => ['Homemade Lemonade', 'With fresh mint.']], diet: ['vegan', 'gluten_free']);
        $this->item($cold, 6000, ['tr' => ['Ayran', 'Köpüklü, bakır tasta.'], 'en' => ['Ayran', 'Frothy yoghurt drink in a copper cup.']], diet: ['vegetarian'], allergens: ['milk']);

        // Breakfast: only at Moda, served 08:00–12:00 (the Moda manager may edit it).
        $breakfast = $this->menu('kahvalti', MenuType::Breakfast, ['tr' => 'Kahvaltı', 'en' => 'Breakfast'], [$moda], servingHours: ['08:00', '12:00']);
        $plates = $this->category($breakfast, ['tr' => 'Kahvaltı tabakları', 'en' => 'Breakfast plates']);
        $this->item($plates, 18000, ['tr' => ['Simit & Peynir Tabağı', 'Sıcak simit, ezine peyniri, domates, zeytin.'], 'en' => ['Simit & Cheese Plate', 'Warm simit, Ezine cheese, tomatoes, olives.']],
            featured: true, diet: ['vegetarian'], allergens: ['gluten', 'sesame', 'milk']);
        $this->item($plates, 42000, ['tr' => ['Serpme Kahvaltı (2 kişilik)', 'Peynirler, bal-kaymak, reçeller, sahanda yumurta, sınırsız çay.'], 'en' => ['Turkish Breakfast Spread (for 2)', 'Cheeses, honey & cream, jams, fried eggs, bottomless tea.']],
            popular: true, diet: ['vegetarian'], allergens: ['gluten', 'eggs', 'milk'], prep: 20);
        $soups = $this->category($breakfast, ['tr' => 'Çorbalar', 'en' => 'Soups']);
        $lentil = $this->item($soups, 14000, ['tr' => ['Mercimek Çorbası', 'Limon ve kıtır ekmek ile.'], 'en' => ['Lentil Soup', 'With lemon and croutons.']],
            diet: ['vegan'], allergens: ['gluten', 'celery']);

        // Sold-out states: per branch.
        LocationItemState::query()->create(['location_id' => $moda->getKey(), 'menu_item_id' => $lentil->getKey(), 'is_sold_out' => true]);
        LocationItemState::query()->create([
            'location_id' => $cihangir->getKey(), 'menu_item_id' => $sutlac->getKey(), 'is_sold_out' => true,
            'sold_out_until' => CarbonImmutable::now('Europe/Istanbul')->endOfDay()->utc(),
        ]);

        // QR codes (random codes from the model); one deep-links to breakfast, one is inactive.
        $moda->qrCodes()->create(['label' => 'Moda — masa kartı']);
        $cihangir->qrCodes()->create(['label' => 'Cihangir — vitrin']);
        $moda->qrCodes()->create(['menu_id' => $breakfast->getKey(), 'label' => 'Moda — kahvaltı']);
        $moda->qrCodes()->create(['label' => 'Pasif kod'])->forceFill(['is_active' => false])->save();
    }

    /**
     * One small business per Phase 7 theme: one branch, one published menu,
     * variants, extras, allergens and a sold-out item, so every theme shows
     * all its parts.
     *
     * @param  array<string, mixed>  $venue
     */
    private function venue(array $venue): void
    {
        $location = $this->location('merkez', true, $venue['location'], $venue['branch']);

        foreach (range(1, 7) as $day) {
            $hours = array_key_exists($day, $venue['hours']) ? $venue['hours'][$day] : $venue['hours']['default'];

            if ($hours !== null) {
                $location->openingHours()->create(['day_of_week' => $day, 'opens_at' => $hours[0], 'closes_at' => $hours[1]]);
            }
        }

        $extras = $venue['extras'] !== null
            ? $this->extraGroup($venue['extras'][0], $venue['extras'][1], $venue['extras'][2], $venue['extras'][3] ?? null)
            : null;
        $menu = $this->menu('menu', MenuType::AllDay, $venue['menu'], [$location]);
        $soldOut = null;

        foreach ($venue['categories'] as $category) {
            $model = $this->category($menu, $category['names']);

            foreach ($category['items'] as $item) {
                $created = $this->item(
                    $model,
                    $item['price'] ?? null,
                    $item['texts'],
                    featured: $item['featured'] ?? false,
                    popular: $item['popular'] ?? false,
                    diet: $item['diet'] ?? [],
                    allergens: $item['allergens'] ?? [],
                    extras: ($item['extras'] ?? false) && $extras !== null ? [$extras] : [],
                    variants: $item['variants'] ?? [],
                    prep: $item['prep'] ?? null,
                    calories: $item['calories'] ?? null,
                );

                if ($item['sold_out'] ?? false) {
                    $soldOut = $created;
                }
            }
        }

        if ($soldOut !== null) {
            LocationItemState::query()->create(['location_id' => $location->getKey(), 'menu_item_id' => $soldOut->getKey(), 'is_sold_out' => true]);
        }

        $location->qrCodes()->create(['label' => $venue['brand']['tr']['name']]);
    }

    /**
     * Sample content of the design files (docs/design/source/Menu{Noon,Ardoise,Kiln,Petal}).
     *
     * @return array<string, array<string, mixed>>
     */
    private function showcaseData(): array
    {
        return [
            'noon' => [
                'theme' => MenuTheme::Minimal,
                'brand' => [
                    'tr' => ['name' => 'noon.', 'tagline' => 'Kahve ve sade yemekler', 'description' => 'İyi kahve, dürüst yemek, telaşsız. Her şey burada, her sabah yapılır.'],
                    'en' => ['name' => 'noon.', 'tagline' => 'Coffee & simple food', 'description' => 'Good coffee, honest food, no fuss. Everything made here, every morning.'],
                ],
                'branch' => ['tr' => 'Karaköy', 'en' => 'Karaköy'],
                'location' => ['address_line' => 'Kemankeş Cd. No:21', 'district' => 'Beyoğlu', 'city' => 'İstanbul', 'phone' => '+90 212 555 03 21'],
                'hours' => ['default' => ['08:00', '16:00']],
                'menu' => ['tr' => 'Menü', 'en' => 'Menu'],
                'extras' => [ExtraSelectionType::Single, ['tr' => 'Süt', 'en' => 'Milk'], [
                    [['tr' => 'İnek sütü', 'en' => 'Whole milk'], 0],
                    [['tr' => 'Yulaf, badem veya soya', 'en' => 'Oat, almond or soy'], 2000],
                ]],
                'categories' => [
                    ['names' => ['tr' => 'Kahve', 'en' => 'Coffee'], 'items' => [
                        ['texts' => ['tr' => ['Filtre kahve', 'Değişen kavurucu, sınırsız tazeleme'], 'en' => ['Drip coffee', 'Rotating roaster, bottomless refills']], 'price' => 9000, 'diet' => ['vegan']],
                        ['texts' => ['tr' => ['Latte', 'Çift shot, buharda süt'], 'en' => ['Latte', 'Double shot, steamed milk']], 'featured' => true, 'popular' => true, 'diet' => ['vegetarian'], 'allergens' => ['milk'], 'extras' => true, 'calories' => 190,
                            'variants' => [[['tr' => '350 ml', 'en' => '12 oz'], 10500, true], [['tr' => '470 ml', 'en' => '16 oz'], 12000, false]]],
                        ['texts' => ['tr' => ['Buzlu latte', 'Buz üstünde, istediğiniz sütle'], 'en' => ['Iced latte', 'Over ice, any milk']], 'price' => 11500, 'diet' => ['vegetarian'], 'allergens' => ['milk'], 'extras' => true],
                    ]],
                    ['names' => ['tr' => 'Kahvaltı', 'en' => 'Breakfast'], 'items' => [
                        ['texts' => ['tr' => ['Avokadolu tost', 'Ekşi maya, limon, pul biber, deniz tuzu'], 'en' => ['Avocado toast', 'Sourdough, lemon, chilli, sea salt']], 'price' => 24000, 'featured' => true, 'popular' => true, 'diet' => ['vegan'], 'allergens' => ['gluten']],
                        ['texts' => ['tr' => ['Tereyağlı kruvasan', 'Her sabah burada pişer'], 'en' => ['Butter croissant', 'Baked in-house every morning']], 'price' => 8500, 'diet' => ['vegetarian'], 'allergens' => ['gluten', 'milk', 'eggs']],
                        ['texts' => ['tr' => ['Granola kase', 'Süzme yoğurt, orman meyveleri, bal'], 'en' => ['Granola bowl', 'Greek yoghurt, berries, honey']], 'price' => 19000, 'diet' => ['vegetarian'], 'allergens' => ['milk', 'nuts']],
                    ]],
                    ['names' => ['tr' => 'Öğle', 'en' => 'Lunch'], 'items' => [
                        ['texts' => ['tr' => ['Yeşil kase', 'Kinoa, yeşillik, tahin, turşu soğan'], 'en' => ['Green bowl', 'Quinoa, greens, tahini, pickled onion']], 'price' => 28000, 'diet' => ['vegan', 'gluten_free'], 'allergens' => ['sesame']],
                        ['texts' => ['tr' => ['Domates çorbası', 'Közlenmiş domates, fesleğen, focaccia'], 'en' => ['Tomato soup', 'Roasted tomato, basil, focaccia']], 'price' => 16000, 'diet' => ['vegan'], 'allergens' => ['gluten'], 'sold_out' => true],
                    ]],
                ],
            ],
            'maison-ardoise' => [
                'theme' => MenuTheme::Bistro,
                'brand' => [
                    'tr' => ['name' => 'Maison Ardoise', 'tagline' => 'Mevsim mutfağı', 'description' => 'On iki masalık bir mevsim mutfağı. Her akşam, o sabah pazardan ne geldiyse onunla yeniden yazılan tek bir kara tahta.'],
                    'en' => ['name' => 'Maison Ardoise', 'tagline' => 'Cuisine de saison', 'description' => 'A seasonal kitchen of twelve tables. One slate, rewritten each evening with whatever the market brought in that morning.'],
                ],
                'branch' => ['tr' => 'Nişantaşı', 'en' => 'Nişantaşı'],
                'location' => ['address_line' => 'Abdi İpekçi Cd. No:14', 'district' => 'Şişli', 'city' => 'İstanbul', 'phone' => '+90 212 555 04 14'],
                'hours' => ['default' => ['19:00', '23:30'], 1 => null], // closed on Mondays
                'menu' => ['tr' => 'Akşam menüsü', 'en' => 'La carte'],
                'extras' => [ExtraSelectionType::Single, ['tr' => 'Pişirme derecesi', 'en' => 'Cuisson'], [
                    [['tr' => 'Az pişmiş', 'en' => 'Bleu'], 0],
                    [['tr' => 'Orta az', 'en' => 'Saignant'], 0],
                    [['tr' => 'Orta', 'en' => 'À point'], 0],
                ]],
                'categories' => [
                    ['names' => ['tr' => 'Başlangıçlar', 'en' => 'Entrées'], 'items' => [
                        ['texts' => ['tr' => ['Dana tartar', 'Bıçakla kıyılmış dana, kapari, yumurta sarısı'], 'en' => ['Beef tartare', 'Hand-cut beef, capers, egg yolk']], 'price' => 89000, 'allergens' => ['eggs', 'mustard']],
                        ['texts' => ['tr' => ['Soğan çorbası', 'Gratine Gruyère, kızarmış ekmek'], 'en' => ['Onion soup', 'Gruyère gratin, toasted bread']], 'price' => 52000, 'diet' => ['vegetarian'], 'allergens' => ['gluten', 'milk']],
                    ]],
                    ['names' => ['tr' => 'Ana yemekler', 'en' => 'Plats'], 'items' => [
                        ['texts' => ['tr' => ['Karabiberli dana bonfile', 'Yeşil karabiber ve konyak sosu, patates püresi, taze fasulye'], 'en' => ['Filet de bœuf au poivre', 'Green peppercorn and Cognac jus, pommes purée, haricots verts']], 'price' => 145000, 'featured' => true, 'popular' => true, 'diet' => ['gluten_free'], 'allergens' => ['milk', 'mustard', 'sulphites'], 'extras' => true, 'prep' => 25],
                        ['texts' => ['tr' => ['Levrek', 'Tereyağı sosu, mevsim sebzeleri'], 'en' => ['Sea bass', 'Beurre blanc, seasonal vegetables']], 'price' => 118000, 'allergens' => ['fish', 'milk'], 'sold_out' => true],
                    ]],
                    ['names' => ['tr' => 'Tatlılar', 'en' => 'Desserts'], 'items' => [
                        ['texts' => ['tr' => ['Çikolatalı sufle', 'Bitter çikolata, vanilyalı dondurma, ahududu'], 'en' => ['Fondant au chocolat', 'Dark chocolate, vanilla ice cream, raspberries']], 'price' => 42000, 'featured' => true, 'diet' => ['vegetarian'], 'allergens' => ['eggs', 'milk', 'gluten']],
                        ['texts' => ['tr' => ['Peynir tabağı', 'Üç olgun peynir, incir reçeli'], 'en' => ['Cheese board', 'Three aged cheeses, fig jam']], 'variants' => [[['tr' => 'Tek kişilik', 'en' => 'For one'], 48000, true], [['tr' => 'İki kişilik', 'en' => 'To share'], 82000, false]], 'diet' => ['vegetarian'], 'allergens' => ['milk']],
                    ]],
                ],
            ],
            'kiln-coffee' => [
                'theme' => MenuTheme::Cafe,
                'brand' => [
                    'tr' => ['name' => 'Kiln Coffee', 'tagline' => 'Nitelikli kahve', 'description' => 'Küçük partili kavurma ve espresso bar. Çekirdekler her salı ve cuma burada kavrulur.'],
                    'en' => ['name' => 'Kiln Coffee', 'tagline' => 'Specialty coffee', 'description' => 'Small-batch roastery and espresso bar. Beans roasted on site every Tuesday and Friday.'],
                ],
                'branch' => ['tr' => 'Moda', 'en' => 'Moda'],
                'location' => ['address_line' => 'Şair Nefi Sk. No:58', 'district' => 'Kadıköy', 'city' => 'İstanbul', 'phone' => '+90 216 555 05 58', 'whatsapp' => '+90 555 555 05 58'],
                'hours' => ['default' => ['07:30', '18:00'], 6 => ['09:00', '17:00'], 7 => ['09:00', '17:00']],
                'menu' => ['tr' => 'Menü', 'en' => 'Menu'],
                'extras' => [ExtraSelectionType::Multiple, ['tr' => 'Ekstralar', 'en' => 'Extras'], [
                    [['tr' => 'Ekstra shot', 'en' => 'Extra shot'], 2500],
                    [['tr' => 'Yulaf sütü', 'en' => 'Oat milk'], 0],
                    [['tr' => 'Badem sütü', 'en' => 'Almond milk'], 2000],
                ], 2],
                'categories' => [
                    ['names' => ['tr' => 'Espresso', 'en' => 'Espresso'], 'items' => [
                        ['texts' => ['tr' => ['Flat white', 'Çift ristretto, ipeksi süt köpüğü'], 'en' => ['Flat white', 'Double ristretto, silky microfoam']], 'featured' => true, 'popular' => true, 'diet' => ['vegetarian'], 'allergens' => ['milk'], 'extras' => true,
                            'variants' => [[['tr' => 'Küçük', 'en' => 'Small'], 9500, true], [['tr' => 'Büyük', 'en' => 'Large'], 11500, false]]],
                        ['texts' => ['tr' => ['Cortado', 'Eşit espresso ve süt'], 'en' => ['Cortado', 'Equal espresso and milk']], 'price' => 9000, 'diet' => ['vegetarian'], 'allergens' => ['milk'], 'extras' => true],
                        ['texts' => ['tr' => ['Cold brew', '18 saat demlenir'], 'en' => ['Cold brew', 'Steeped for 18 hours']], 'price' => 11000, 'featured' => true, 'diet' => ['vegan']],
                    ]],
                    ['names' => ['tr' => 'Fırından', 'en' => 'From the oven'], 'items' => [
                        ['texts' => ['tr' => ['Kruvasan', 'Bol tereyağlı, her gün taze'], 'en' => ['Croissant', 'All-butter, baked daily']], 'price' => 8000, 'diet' => ['vegetarian'], 'allergens' => ['gluten', 'milk', 'eggs']],
                        ['texts' => ['tr' => ['Muzlu kek', 'Ceviz ve esmer şeker'], 'en' => ['Banana bread', 'Walnuts and brown sugar']], 'price' => 9500, 'diet' => ['vegetarian'], 'allergens' => ['gluten', 'nuts', 'eggs'], 'sold_out' => true],
                    ]],
                    ['names' => ['tr' => 'Çekirdekler', 'en' => 'Beans'], 'items' => [
                        ['texts' => ['tr' => ['Ember ev harmanı, 250 g', 'Kakao, erik, esmer şeker'], 'en' => ['Ember house blend, 250 g', 'Cocoa, plum, brown sugar']], 'price' => 42000, 'diet' => ['vegan']],
                    ]],
                ],
            ],
            'petal-bakehouse' => [
                'theme' => MenuTheme::Grid,
                'brand' => [
                    'tr' => ['name' => 'Petal Bakehouse', 'tagline' => 'Butik pastane', 'description' => 'Kekler, hamur işleri ve yavaş sabahlar. Her şey alt katta, sabah beşten itibaren pişer.'],
                    'en' => ['name' => 'Petal Bakehouse', 'tagline' => 'Boutique bakery', 'description' => 'Cakes, pastries and slow mornings. Everything baked downstairs, from 5am.'],
                ],
                'branch' => ['tr' => 'Bebek', 'en' => 'Bebek'],
                'location' => ['address_line' => 'Cevdetpaşa Cd. No:88', 'district' => 'Beşiktaş', 'city' => 'İstanbul', 'phone' => '+90 212 555 06 88'],
                'hours' => ['default' => ['08:00', '18:00']],
                'menu' => ['tr' => 'Menü', 'en' => 'Menu'],
                'extras' => null,
                'categories' => [
                    ['names' => ['tr' => 'Kekler', 'en' => 'Cakes'], 'items' => [
                        ['texts' => ['tr' => ['Ahududulu katlı kek', 'Vanilyalı pandispanya, ahududu reçeli, mascarpone krema'], 'en' => ['Raspberry layer cake', 'Vanilla sponge, raspberry jam, mascarpone cream']], 'featured' => true, 'popular' => true, 'diet' => ['vegetarian'], 'allergens' => ['gluten', 'eggs', 'milk'],
                            'variants' => [[['tr' => 'Dilim', 'en' => 'Slice'], 17500, true], [['tr' => 'Bütün kek (12 kişilik)', 'en' => 'Whole cake (serves 12)'], 145000, false]]],
                        ['texts' => ['tr' => ['Böğürtlenli defne keki', 'Hafta sonuna özel'], 'en' => ['Blackberry & bay cake', 'Weekend only']], 'price' => 19000, 'diet' => ['vegetarian'], 'allergens' => ['gluten', 'eggs', 'milk']],
                        ['texts' => ['tr' => ['Limonlu kek', 'Glutensiz, badem unuyla'], 'en' => ['Lemon drizzle', 'Gluten-free, with almond flour']], 'price' => 15000, 'diet' => ['gluten_free'], 'allergens' => ['nuts', 'eggs'], 'sold_out' => true],
                    ]],
                    ['names' => ['tr' => 'Kurabiyeler ve makaronlar', 'en' => 'Cookies & macarons'], 'items' => [
                        ['texts' => ['tr' => ['Makaron kutusu (6 adet)', 'Mevsimin tatları'], 'en' => ['Macaron box of 6', 'Flavours of the season']], 'price' => 36000, 'featured' => true, 'diet' => ['gluten_free'], 'allergens' => ['nuts', 'eggs', 'milk']],
                        ['texts' => ['tr' => ['Çilekli cupcake', 'Taze çilek, krem peynir'], 'en' => ['Strawberry cupcake', 'Fresh strawberries, cream cheese']], 'price' => 11500, 'diet' => ['vegetarian'], 'allergens' => ['gluten', 'eggs', 'milk']],
                        ['texts' => ['tr' => ['Bademli kruvasan', '10:30\'da fırından çıkar'], 'en' => ['Almond croissant', 'Out of the oven at 10:30']], 'price' => 12000, 'diet' => ['vegetarian'], 'allergens' => ['gluten', 'nuts', 'milk', 'eggs']],
                    ]],
                ],
            ],
        ];
    }
}
