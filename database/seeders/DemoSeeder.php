<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ExtraSelectionType;
use App\Enums\MenuTheme;
use App\Enums\MenuType;
use App\Enums\PlanFeature;
use App\Enums\PlatformRole;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\Extra;
use App\Models\ExtraGroup;
use App\Models\Location;
use App\Models\LocationItemState;
use App\Models\LocationMenu;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Plan;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Development / showcase data (never run in production):
 *
 * - platform users: admin@restano.test (super_admin), destek@restano.test (platform_staff)
 * - "Kahve Konağı" (kahve-konagi): two branches, two menus, variants, extras,
 *   allergens, sold-out states, hours, QR codes; owner, manager and branch staff
 * - "Eski Fırın" (eski-firin): a suspended business (unavailable page)
 *
 * Venue names come from the design files' sample content. Every password is
 * "password". Idempotent: an existing demo tenant or user is left untouched.
 */
class DemoSeeder extends Seeder
{
    public const string PASSWORD = 'password';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->platformUsers();
            $plan = $this->demoPlan();
            $this->kahveKonagi($plan);
            $this->suspendedTenant($plan);
        });

        app(TenantContext::class)->clear();
    }

    private function platformUsers(): void
    {
        $this->user('Platform Yöneticisi', 'admin@restano.test', PlatformRole::SuperAdmin);
        $this->user('Platform Destek', 'destek@restano.test', PlatformRole::PlatformStaff);
    }

    /**
     * Non-public plan with room for the showcase (not listed on the pricing page).
     */
    private function demoPlan(): Plan
    {
        $plan = Plan::query()->where('code', 'demo')->first() ?? new Plan;
        $plan->forceFill(['code' => 'demo', 'is_active' => true, 'is_public' => false, 'is_featured' => false, 'trial_days' => 365, 'sort_order' => 99])->save();

        foreach (['tr' => 'Demo', 'en' => 'Demo'] as $locale => $name) {
            $plan->translations()->updateOrCreate(['locale' => $locale], ['name' => $name]);
        }

        $limits = [
            'max_locations' => 5, 'max_menus' => 10, 'max_items' => 500, 'max_languages' => 3, 'max_qr_codes' => 50,
            'premium_themes' => 1, 'custom_domain' => 0, 'remove_branding' => 0, 'qr_analytics' => 0,
        ];

        foreach ($limits as $feature => $value) {
            $plan->limits()->updateOrCreate(['feature' => PlanFeature::from($feature)], ['limit_value' => $value]);
        }

        return $plan;
    }

    private function kahveKonagi(Plan $plan): void
    {
        if (Tenant::withTrashed()->where('slug', 'kahve-konagi')->exists()) {
            $this->command?->warn('kahve-konagi already exists; skipped.');

            return;
        }

        $tenant = $this->tenant('kahve-konagi', [
            'tr' => ['name' => 'Kahve Konağı', 'tagline' => 'Közde Türk kahvesi', 'description' => "Közde pişen Türk kahvesi, demlikte çay ve ev yapımı tatlılar. Moda'nın arka sokaklarında, yüz yıllık bir konakta."],
            'en' => ['name' => 'Kahve Konağı', 'tagline' => 'Turkish coffee over embers', 'description' => 'Turkish coffee brewed over embers, steeped tea and homemade desserts — in a century-old mansion in the back streets of Moda.'],
        ]);

        $context = app(TenantContext::class);
        $context->set($tenant);

        foreach (['tr', 'en'] as $order => $locale) {
            $tenant->locales()->create(['locale' => $locale, 'sort_order' => $order]);
        }

        app(SubscriptionService::class)->startTrial($tenant, $plan);

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

        // Stable QR codes (fixed for the demo; normally random).
        $this->qr('KNKM0DA001', $moda, null, 'Moda — masa kartı');
        $this->qr('KNKC1HANG1', $cihangir, null, 'Cihangir — vitrin');
        $this->qr('KNKKAHVE01', $moda, $breakfast, 'Moda — kahvaltı');
        $this->qr('KNKPAS1F01', $moda, null, 'Pasif kod')->forceFill(['is_active' => false])->save();

        // Members (email verified: the business panel requires it).
        $this->member($tenant, 'Selin Konak', 'sahip@kahvekonagi.test', TenantRole::Owner);
        $this->member($tenant, 'Murat Demir', 'mudur@kahvekonagi.test', TenantRole::Manager, [$moda]);
        $this->member($tenant, 'Ece Yıldız', 'personel@kahvekonagi.test', TenantRole::BranchStaff, [$moda]);

        $context->clear();
    }

    private function suspendedTenant(Plan $plan): void
    {
        if (Tenant::withTrashed()->where('slug', 'eski-firin')->exists()) {
            return;
        }

        $tenant = $this->tenant('eski-firin', ['tr' => ['name' => 'Eski Fırın']], TenantStatus::Suspended);
        $context = app(TenantContext::class);
        $context->set($tenant);

        $tenant->locales()->create(['locale' => 'tr', 'sort_order' => 0]);
        app(SubscriptionService::class)->startTrial($tenant, $plan);
        $location = $this->location('merkez', true, ['city' => 'İzmir'], ['tr' => 'Merkez']);
        $this->menu('menu', MenuType::AllDay, ['tr' => 'Menü'], [$location]);
        $this->member($tenant, 'Hakan Fırıncı', 'sahip@eskifirin.test', TenantRole::Owner);

        $context->clear();
    }

    /**
     * @param  array<string, array<string, string>>  $translations
     */
    private function tenant(string $slug, array $translations, TenantStatus $status = TenantStatus::Active): Tenant
    {
        $tenant = new Tenant(['slug' => $slug, 'currency' => 'TRY', 'default_locale' => 'tr', 'theme' => MenuTheme::Classic]);
        $tenant->forceFill(['status' => $status])->save();

        foreach ($translations as $locale => $values) {
            $tenant->translations()->create(['locale' => $locale, ...$values]);
        }

        return $tenant;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>  $names
     * @param  array<string, string>  $descriptions
     */
    private function location(string $slug, bool $default, array $attributes, array $names, array $descriptions = []): Location
    {
        $location = new Location(['slug' => $slug, 'is_active' => true, 'timezone' => 'Europe/Istanbul', 'country_code' => 'TR', ...$attributes]);
        $location->forceFill(['is_default' => $default, 'sort_order' => $default ? 0 : 1])->save();

        foreach ($names as $locale => $name) {
            $location->translations()->create(['locale' => $locale, 'name' => $name, 'description' => $descriptions[$locale] ?? null]);
        }

        return $location;
    }

    /**
     * @param  array<string, string>  $names
     * @param  list<Location>  $locations
     * @param  array{string, string}|null  $servingHours
     */
    private function menu(string $slug, MenuType $type, array $names, array $locations, ?array $servingHours = null): Menu
    {
        $menu = Menu::query()->create(['slug' => $slug, 'type' => $type, 'is_published' => true, 'sort_order' => $type === MenuType::Breakfast ? 0 : 1]);

        foreach ($names as $locale => $name) {
            $menu->translations()->create(['locale' => $locale, 'name' => $name]);
        }

        foreach ($locations as $location) {
            $attachment = LocationMenu::query()->create([
                'location_id' => $location->getKey(), 'menu_id' => $menu->getKey(), 'is_active' => true,
                'sort_order' => $type === MenuType::Breakfast ? 0 : 1,
            ]);

            if ($servingHours !== null) {
                foreach (range(1, 7) as $day) {
                    $attachment->hours()->create(['day_of_week' => $day, 'starts_at' => $servingHours[0], 'ends_at' => $servingHours[1]]);
                }
            }
        }

        return $menu;
    }

    /**
     * @param  array<string, string>  $names
     */
    private function category(Menu $menu, array $names): MenuCategory
    {
        $category = $menu->categories()->create(['is_visible' => true, 'sort_order' => (int) $menu->categories()->max('sort_order') + 1]);

        foreach ($names as $locale => $name) {
            $category->translations()->create(['locale' => $locale, 'name' => $name]);
        }

        return $category;
    }

    /**
     * @param  array<string, array{string, string}>  $texts  locale => [name, description]
     * @param  list<string>  $diet
     * @param  list<string>  $allergens
     * @param  list<ExtraGroup>  $extras
     * @param  list<array{array<string, string>, int, bool}>  $variants
     */
    private function item(
        MenuCategory $category,
        ?int $price,
        array $texts,
        bool $featured = false,
        bool $popular = false,
        array $diet = [],
        array $allergens = [],
        array $extras = [],
        array $variants = [],
        ?int $prep = null,
        ?int $calories = null,
    ): MenuItem {
        $item = $category->items()->create([
            'price' => $price, 'is_visible' => true, 'is_available' => true, 'is_featured' => $featured, 'is_popular' => $popular,
            'preparation_minutes' => $prep, 'calories' => $calories, 'sort_order' => (int) $category->items()->max('sort_order') + 1,
        ]);

        foreach ($texts as $locale => [$name, $description]) {
            $item->translations()->create(['locale' => $locale, 'name' => $name, 'description' => $description]);
        }

        foreach ($diet as $label) {
            $item->dietaryLabels()->create(['label' => $label]);
        }

        foreach ($allergens as $allergen) {
            $item->allergens()->create(['allergen' => $allergen]);
        }

        foreach ($extras as $position => $group) {
            $item->extraGroups()->attach($group->getKey(), ['sort_order' => $position]);
        }

        foreach ($variants as $position => [$names, $variantPrice, $isDefault]) {
            $variant = $item->variants()->create(['price' => $variantPrice, 'is_default' => $isDefault, 'is_available' => true, 'sort_order' => $position]);

            foreach ($names as $locale => $name) {
                $variant->translations()->create(['locale' => $locale, 'name' => $name]);
            }
        }

        return $item;
    }

    /**
     * @param  array<string, string>  $names
     * @param  list<array{array<string, string>, int}>  $options
     */
    private function extraGroup(ExtraSelectionType $type, array $names, array $options, ?int $max = null): ExtraGroup
    {
        $group = ExtraGroup::query()->create([
            'selection_type' => $type, 'min_select' => 0, 'max_select' => $type === ExtraSelectionType::Single ? 1 : $max,
            'is_required' => false, 'sort_order' => (int) ExtraGroup::query()->max('sort_order') + 1,
        ]);

        foreach ($names as $locale => $name) {
            $group->translations()->create(['locale' => $locale, 'name' => $name]);
        }

        foreach ($options as $position => [$optionNames, $delta]) {
            /** @var Extra $extra */
            $extra = $group->extras()->create(['price_delta' => $delta, 'is_available' => true, 'sort_order' => $position]);

            foreach ($optionNames as $locale => $name) {
                $extra->translations()->create(['locale' => $locale, 'name' => $name]);
            }
        }

        return $group;
    }

    private function qr(string $code, Location $location, ?Menu $menu, string $label): QrCode
    {
        $qr = new QrCode(['location_id' => $location->getKey(), 'menu_id' => $menu?->getKey(), 'label' => $label, 'is_active' => true]);
        $qr->code = $code;
        $qr->save();

        return $qr;
    }

    /**
     * @param  list<Location>  $locations
     */
    private function member(Tenant $tenant, string $name, string $email, TenantRole $role, array $locations = []): void
    {
        $user = $this->user($name, $email);

        $membership = TenantMembership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->first()
            ?? new TenantMembership;
        $membership->forceFill(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), 'role' => $role, 'is_active' => true])->save();

        if ($locations !== []) {
            $membership->locations()->syncWithoutDetaching(array_map(fn (Location $location) => $location->getKey(), $locations));
        }
    }

    private function user(string $name, string $email, ?PlatformRole $role = null): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            return $user;
        }

        $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => Hash::make(self::PASSWORD)]);
        $user->forceFill(['email_verified_at' => now(), 'platform_role' => $role])->save();

        return $user;
    }
}
