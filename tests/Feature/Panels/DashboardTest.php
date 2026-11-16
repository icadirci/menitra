<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\MenuTheme;
use App\Enums\SubscriptionStatus;
use App\Events\QrCodeScanned;
use App\Filament\App\Widgets\BranchOverviewWidget;
use App\Filament\App\Widgets\MenuStatusWidget;
use App\Filament\App\Widgets\OnboardingChecklistWidget;
use App\Filament\App\Widgets\PlanUsageWidget;
use App\Filament\App\Widgets\SubscriptionStatusWidget;
use App\Listeners\RecordQrCodeScan;
use App\Models\Location;
use App\Models\LocationItemState;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\QrCode;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\Dashboard\BusinessDashboard;
use App\Services\Dashboard\OnboardingStep;
use App\Services\Menu\MenuUrlGenerator;
use App\Services\Subscription\FeatureGate;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private Tenant $tenant;

    private Location $moda;

    private Location $cihangir;

    /** Attached to Moda only. */
    private Menu $breakfast;

    /** Attached to Cihangir only. */
    private Menu $dinner;

    private MenuCategory $bakery;

    private MenuItem $simit;

    private MenuItem $kofte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->subscribedTenant('konak', ['max_locations' => 5, 'max_qr_codes' => 10]);
        $this->moda = $this->location($this->tenant, 'moda', true);
        $this->cihangir = $this->location($this->tenant, 'cihangir');
        $this->breakfast = $this->menu($this->tenant, 'kahvalti', [$this->moda], name: 'Kahvaltı');
        $this->dinner = $this->menu($this->tenant, 'aksam', [$this->cihangir], published: false, name: 'Akşam');
        $this->bakery = $this->category($this->breakfast, 'Fırın');
        $this->simit = $this->item($this->bakery, 'Simit');
        $this->kofte = $this->item($this->category($this->dinner, 'Ana yemek'), 'Köfte');
    }

    private function owner(): User
    {
        return TenantMembership::factory()->for($this->tenant)->create()->user;
    }

    private function member(string $role, Location ...$locations): User
    {
        $membership = TenantMembership::factory()->for($this->tenant)->{$role}()->create();
        $membership->locations()->attach(array_map(fn (Location $location) => $location->getKey(), $locations));

        return $membership->user;
    }

    private function livewire(User $user, string $component, ?Tenant $tenant = null): Testable
    {
        $tenant ??= $this->tenant;
        $this->forgetDashboardState();
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($tenant);
        app(TenantContext::class)->set($tenant);

        return Livewire::test($component);
    }

    /**
     * A fresh request's view of the app's scoped services (Filament's own
     * scoped manager must survive, so not forgetScopedInstances()).
     */
    private function forgetDashboardState(): void
    {
        foreach ([TenantContext::class, FeatureGate::class, BusinessDashboard::class] as $abstract) {
            app()->forgetInstance($abstract);
        }
    }

    private function qr(Location $location, string $label, bool $active = true): QrCode
    {
        return $this->inTenant($this->tenant, fn (): QrCode => QrCode::query()->create([
            'location_id' => $location->getKey(),
            'label' => $label,
            'is_active' => $active,
        ]));
    }

    private function soldOut(Location $location, MenuItem $item, mixed $until = null): void
    {
        $this->inTenant($this->tenant, fn () => LocationItemState::query()->create([
            'location_id' => $location->getKey(),
            'menu_item_id' => $item->getKey(),
            'is_sold_out' => true,
            'sold_out_until' => $until,
        ]));
    }

    /**
     * @return array<string, bool>
     */
    private function steps(): array
    {
        $this->forgetDashboardState();

        return $this->inTenant($this->tenant, fn (): array => collect(app(BusinessDashboard::class)->steps())
            ->mapWithKeys(fn (OnboardingStep $step): array => [$step->key => $step->done])
            ->all());
    }

    // --- Role visibility -------------------------------------------------

    public function test_owner_sees_every_widget(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get('/app/konak')
            ->assertOk()
            ->assertSee(__('panels.app.dashboard.onboarding.heading'))
            ->assertSee(__('panels.app.dashboard.subscription.heading'))
            ->assertSee(__('panels.app.dashboard.usage.heading'))
            ->assertSee(__('panels.app.dashboard.branches.heading'))
            ->assertSee(__('panels.app.dashboard.menus.heading'));
    }

    public function test_manager_sees_branch_and_menu_widgets_only(): void
    {
        $manager = $this->member('manager', $this->moda);

        $this->actingAs($manager)->get('/app/konak')
            ->assertOk()
            ->assertDontSee(__('panels.app.dashboard.onboarding.heading'))
            ->assertDontSee(__('panels.app.dashboard.subscription.heading'))
            ->assertDontSee(__('panels.app.dashboard.usage.heading'))
            ->assertSee(__('panels.app.dashboard.branches.heading'))
            ->assertSee(__('panels.app.dashboard.menus.heading'));

        $this->livewire($manager, OnboardingChecklistWidget::class)->assertForbidden();
        $this->livewire($manager, SubscriptionStatusWidget::class)->assertForbidden();
        $this->livewire($manager, PlanUsageWidget::class)->assertForbidden();
    }

    public function test_branch_staff_sees_only_the_branch_widget(): void
    {
        $staff = $this->member('branchStaff', $this->moda);

        $this->actingAs($staff)->get('/app/konak')
            ->assertOk()
            ->assertSee(__('panels.app.dashboard.branches.heading'))
            ->assertDontSee(__('panels.app.dashboard.menus.heading'))
            ->assertDontSee(__('panels.app.dashboard.subscription.heading'))
            ->assertDontSee(__('panels.app.dashboard.onboarding.heading'));

        $this->livewire($staff, MenuStatusWidget::class)->assertForbidden();
    }

    public function test_member_of_a_suspended_tenant_gets_403_on_the_dashboard(): void
    {
        $suspended = Tenant::factory()->suspended()->create(['slug' => 'eski']);
        $user = TenantMembership::factory()->for($suspended)->create()->user;

        $this->actingAs($user)->get('/app/eski')->assertForbidden()->assertSee(__('tenancy.suspended'));
    }

    // --- Branch overview: scope, links, stock, QR --------------------------

    public function test_branch_widget_is_limited_to_accessible_branches(): void
    {
        $other = $this->subscribedTenant('baska');
        $foreign = $this->location($other, 'yabanci', true);

        $this->livewire($this->owner(), BranchOverviewWidget::class)
            ->assertCanSeeTableRecords([$this->moda, $this->cihangir])
            ->assertCanNotSeeTableRecords([$foreign]);

        $this->livewire($this->member('manager', $this->moda), BranchOverviewWidget::class)
            ->assertCanSeeTableRecords([$this->moda])
            ->assertCanNotSeeTableRecords([$this->cihangir, $foreign]);

        $this->livewire($this->member('branchStaff', $this->cihangir), BranchOverviewWidget::class)
            ->assertCanSeeTableRecords([$this->cihangir])
            ->assertCanNotSeeTableRecords([$this->moda, $foreign]);
    }

    public function test_branch_widget_shows_public_link_and_current_sold_out_count(): void
    {
        $moreItem = $this->item($this->bakery, 'Poğaça');
        $this->soldOut($this->moda, $this->simit);                     // indefinitely
        $this->soldOut($this->moda, $moreItem, now()->addHour());      // until later today
        $this->soldOut($this->cihangir, $this->kofte, now()->subHour()); // already back on sale

        $url = app(MenuUrlGenerator::class)->location('konak', 'moda');

        $this->livewire($this->owner(), BranchOverviewWidget::class)
            ->assertTableColumnStateSet('public_url', $url, $this->moda)
            ->assertTableColumnStateSet('sold_out_count', __('panels.app.dashboard.branches.sold_out_count', ['count' => 2]), $this->moda)
            ->assertTableColumnStateSet('sold_out_count', __('panels.app.dashboard.branches.sold_out_none'), $this->cihangir);
    }

    public function test_default_qr_is_the_oldest_active_code_and_downloads_for_every_role(): void
    {
        $this->qr($this->moda, 'Eski kod', active: false);
        $this->qr($this->moda, 'Vitrin');
        $this->qr($this->moda, 'Masa 4');

        $staff = $this->member('branchStaff', $this->moda);

        $this->livewire($staff, BranchOverviewWidget::class)
            ->assertTableColumnStateSet('default_qr', 'Vitrin', $this->moda)
            ->assertTableActionVisible('downloadPng', $this->moda)
            ->callTableAction('downloadSvg', $this->moda)
            ->assertFileDownloaded();

        // A branch without an active code offers no download.
        $this->livewire($this->owner(), BranchOverviewWidget::class)
            ->assertTableActionHidden('downloadSvg', $this->cihangir)
            ->assertTableColumnStateSet('default_qr', __('panels.app.dashboard.branches.no_qr'), $this->cihangir);
    }

    public function test_stock_link_preselects_the_branch_on_the_stock_page(): void
    {
        $this->soldOut($this->cihangir, $this->kofte);
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get('/app/konak/stock?'.http_build_query(['filters' => ['location' => ['value' => $this->cihangir->getKey()]]]))
            ->assertOk()
            ->assertSee('Köfte')
            ->assertDontSee('Simit');

        // Another member's branch in the query string falls back to their own.
        $staff = $this->member('branchStaff', $this->moda);
        $this->actingAs($staff)
            ->get('/app/konak/stock?'.http_build_query(['filters' => ['location' => ['value' => $this->cihangir->getKey()]]]))
            ->assertOk()
            ->assertSee('Simit')
            ->assertDontSee('Köfte');
    }

    // --- Menu publication --------------------------------------------------

    public function test_menu_widget_scopes_menus_by_role(): void
    {
        $this->livewire($this->owner(), MenuStatusWidget::class)
            ->assertCanSeeTableRecords([$this->breakfast, $this->dinner])
            ->assertTableColumnStateSet('is_published', __('panels.app.dashboard.menus.draft'), $this->dinner)
            ->assertTableColumnStateSet('active_locations_count', 1, $this->breakfast)
            ->assertTableColumnStateSet('items_count', 1, $this->breakfast);

        $this->livewire($this->member('manager', $this->moda), MenuStatusWidget::class)
            ->assertCanSeeTableRecords([$this->breakfast])
            ->assertCanNotSeeTableRecords([$this->dinner]);
    }

    // --- Subscription and plan usage ----------------------------------------

    public function test_subscription_widget_shows_trial_and_online_state(): void
    {
        $subscription = $this->inTenant($this->tenant, fn () => Subscription::query()->whereNull('ended_at')->firstOrFail());

        $this->livewire($this->owner(), SubscriptionStatusWidget::class)
            ->assertSee(SubscriptionStatus::Trialing->label())
            ->assertSee(__('panels.app.subscription.fields.trial_ends_at'))
            ->assertSee(__('panels.app.subscription.fields.access_until'))
            ->assertSee($subscription->accessUntil()->setTimezone('Europe/Istanbul')->locale(app()->getLocale())->isoFormat('LL'))
            ->assertSee(__('panels.app.dashboard.subscription.online'));
    }

    public function test_without_a_subscription_the_dashboard_says_so_and_the_menu_is_offline(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'abonesiz']);
        $this->location($tenant, 'merkez', true);
        $owner = TenantMembership::factory()->for($tenant)->create()->user;

        $this->actingAs($owner)->get('/app/abonesiz')
            ->assertOk()
            ->assertSee(__('panels.app.dashboard.subscription.none'));

        $this->livewire($owner, SubscriptionStatusWidget::class, $tenant)
            ->assertSee(__('panels.app.dashboard.subscription.none'))
            ->assertSee(__('panels.app.dashboard.subscription.offline'));

        $this->livewire($owner, PlanUsageWidget::class, $tenant)
            ->assertSee(__('panels.app.dashboard.usage.description_none'))
            ->assertSee(__('panels.app.dashboard.usage.not_included'));

        $this->livewire($owner, BranchOverviewWidget::class, $tenant)
            ->assertSee(__('panels.app.dashboard.branches.offline'));
    }

    public function test_expired_trial_shows_offline(): void
    {
        $this->travel(30)->days();

        $this->livewire($this->owner(), SubscriptionStatusWidget::class)
            ->assertSee(__('panels.app.dashboard.subscription.offline'))
            ->assertSee(__('panels.app.dashboard.subscription.ended'));
    }

    public function test_subscription_widget_shows_grace_with_its_end_date_and_the_menu_online(): void
    {
        $this->travel(14)->days();
        $subscription = $this->inTenant($this->tenant, function (): Subscription {
            $subscription = Subscription::query()->whereNull('ended_at')->firstOrFail();
            app(SubscriptionService::class)->processDue($subscription);

            return $subscription;
        });

        $this->assertSame(SubscriptionStatus::Grace, $subscription->status);
        $this->livewire($this->owner(), SubscriptionStatusWidget::class)
            ->assertSee(__('panels.app.dashboard.subscription.grace', ['status' => SubscriptionStatus::Grace->label()]))
            ->assertSee(__('panels.app.subscription.fields.grace_ends_at'))
            ->assertSee($subscription->grace_ends_at->setTimezone('Europe/Istanbul')->locale(app()->getLocale())->isoFormat('LL'))
            ->assertSee(__('panels.app.dashboard.subscription.online'));
    }

    public function test_subscription_widget_shows_an_expired_subscription_and_the_menu_offline(): void
    {
        $this->travel(30)->days();
        $this->inTenant($this->tenant, fn () => app(SubscriptionService::class)
            ->processDue(Subscription::query()->whereNull('ended_at')->firstOrFail()));

        $this->livewire($this->owner(), SubscriptionStatusWidget::class)
            ->assertSee(__('panels.app.dashboard.subscription.expired', ['status' => SubscriptionStatus::Expired->label()]))
            ->assertSee(__('panels.app.subscription.fields.ended_at'))
            ->assertSee(__('panels.app.dashboard.subscription.expired_help'))
            ->assertSee(__('panels.app.dashboard.subscription.offline'))
            ->assertDontSee(__('panels.app.dashboard.subscription.none'));

        $this->livewire($this->owner(), PlanUsageWidget::class)
            ->assertSee(__('panels.app.dashboard.usage.description_none'));

        $this->livewire($this->owner(), BranchOverviewWidget::class)
            ->assertSee(__('panels.app.dashboard.branches.offline'));
    }

    public function test_subscription_widget_marks_a_cancellation(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $service = app(SubscriptionService::class);
            $subscription = Subscription::query()->whereNull('ended_at')->firstOrFail();
            $service->activate($subscription, now()->toImmutable(), now()->toImmutable()->addDays(30));
            $service->cancel($subscription);
        });

        $this->livewire($this->owner(), SubscriptionStatusWidget::class)
            ->assertSee(SubscriptionStatus::Active->label())
            ->assertSee(__('panels.app.subscription.fields.cancel_at_period_end_yes'))
            ->assertSee(__('panels.app.dashboard.subscription.online'));
    }

    public function test_plan_usage_comes_from_the_feature_gate(): void
    {
        $this->livewire($this->owner(), PlanUsageWidget::class)
            ->assertSee(__('plans.usage', ['used' => 2, 'limit' => 5]))   // branches
            ->assertSee(__('plans.usage', ['used' => 2, 'limit' => 10])); // menus
    }

    // --- Onboarding checklist ---------------------------------------------

    public function test_checklist_steps_are_derived_from_data(): void
    {
        $this->assertSame([
            'profile' => false,
            'items' => true,
            'publish' => true,
            'hours' => false,
            'theme' => false,
            'qr' => false,
        ], $this->steps());
    }

    public function test_checklist_steps_turn_done_and_the_widget_disappears(): void
    {
        $fresh = $this->subscribedTenant('yeni');
        $branch = $this->location($fresh, 'merkez', true);
        $owner = TenantMembership::factory()->for($fresh)->create()->user;
        $this->tenant = $fresh;

        $this->assertSame(array_fill_keys(['profile', 'items', 'publish', 'hours', 'theme', 'qr'], false), $this->steps());

        $this->actingAs($owner)->get('/app/yeni')
            ->assertOk()
            ->assertSee(__('panels.app.dashboard.onboarding.description', ['done' => 0, 'total' => 6]));

        // An unpublished menu, or one on an inactive branch, isn't "published".
        $menu = $this->menu($fresh, 'ana', [$branch], published: false);
        $this->item($this->category($menu, 'Tatlı'), 'Baklava');
        $this->assertTrue($this->steps()['items']);
        $this->assertFalse($this->steps()['publish']);
        $menu->forceFill(['is_published' => true])->save();
        $this->assertTrue($this->steps()['publish']);

        $fresh->forceFill(['logo_path' => 'tenants/'.$fresh->public_id.'/logo/x.png'])->save();
        $this->inTenant($fresh, fn () => $branch->openingHours()->create(['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '22:00']));

        // Theme: the registration row alone isn't a choice; a later save is.
        $setting = $this->inTenant($fresh, fn () => ThemeSetting::query()->create(['theme' => MenuTheme::Classic, 'settings' => []]));
        $this->assertFalse($this->steps()['theme']);
        $this->inTenant($fresh, fn () => $setting->forceFill(['updated_at' => now()->addMinute()])->save());
        $this->assertTrue($this->steps()['theme']);

        $qr = $this->inTenant($fresh, fn (): QrCode => $branch->qrCodes()->create(['label' => 'Kapı']));
        $this->assertFalse($this->steps()['qr']);
        app(RecordQrCodeScan::class)->handle(new QrCodeScanned((int) $fresh->getKey(), (int) $qr->getKey(), time()));

        $fresh->refresh();
        $this->assertNotContains(false, $this->steps());

        $this->actingAs($owner->fresh())->get('/app/yeni')
            ->assertOk()
            ->assertDontSee(__('panels.app.dashboard.onboarding.heading'))
            ->assertSee(__('panels.app.dashboard.branches.heading'));
    }

    public function test_checklist_does_not_see_another_tenants_data(): void
    {
        $other = $this->subscribedTenant('baska');
        $branch = $this->location($other, 'merkez', true);
        $this->inTenant($other, fn () => $branch->openingHours()->create(['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '22:00']));
        $code = $this->inTenant($other, fn (): QrCode => $branch->qrCodes()->create(['label' => 'Kapı']));
        DB::table('qr_codes')->where('id', $code->getKey())->update(['last_scanned_at' => now()]);

        $steps = $this->steps();
        $this->assertFalse($steps['hours']);
        $this->assertFalse($steps['qr']);
    }

    // --- QR scan recording ---------------------------------------------------

    public function test_scan_listener_records_last_scan_throttled_and_tenant_bound(): void
    {
        $code = $this->qr($this->moda, 'Vitrin');
        $listener = app(RecordQrCodeScan::class);
        $scannedAt = fn (): ?string => DB::table('qr_codes')->where('id', $code->getKey())->value('last_scanned_at');

        // Wrong tenant id: nothing is written.
        $other = $this->subscribedTenant('baska');
        $listener->handle(new QrCodeScanned((int) $other->getKey(), (int) $code->getKey(), time()));
        $this->assertNull($scannedAt());

        $first = now()->toImmutable()->subHours(2)->startOfSecond();
        $listener->handle(new QrCodeScanned((int) $this->tenant->getKey(), (int) $code->getKey(), $first->getTimestamp()));
        $this->assertSame($first->getTimestamp(), strtotime((string) $scannedAt().' UTC'));

        // Within the throttle window: unchanged.
        $listener->handle(new QrCodeScanned((int) $this->tenant->getKey(), (int) $code->getKey(), $first->addMinutes(10)->getTimestamp()));
        $this->assertSame($first->getTimestamp(), strtotime((string) $scannedAt().' UTC'));

        // After it: updated.
        $later = $first->addHours(2);
        $listener->handle(new QrCodeScanned((int) $this->tenant->getKey(), (int) $code->getKey(), $later->getTimestamp()));
        $this->assertSame($later->getTimestamp(), strtotime((string) $scannedAt().' UTC'));
    }

    public function test_scanning_a_code_through_the_redirect_records_the_scan(): void
    {
        $code = $this->qr($this->moda, 'Vitrin');

        $this->get(app(MenuUrlGenerator::class)->qr($code->code))->assertRedirect();

        $this->assertNotNull(DB::table('qr_codes')->where('id', $code->getKey())->value('last_scanned_at'));
    }

    // --- Query counts --------------------------------------------------------

    /**
     * @return array<class-string, int>
     */
    private function queryCounts(User $user): array
    {
        $counts = [];

        foreach ([OnboardingChecklistWidget::class, SubscriptionStatusWidget::class, PlanUsageWidget::class, BranchOverviewWidget::class, MenuStatusWidget::class] as $widget) {
            $user = $user->fresh();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->livewire($user, $widget);
            $counts[$widget] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        return $counts;
    }

    public function test_dashboard_queries_do_not_grow_with_branches_and_menus(): void
    {
        $owner = $this->owner();
        $this->qr($this->moda, 'Vitrin');
        $this->soldOut($this->moda, $this->simit);

        $before = $this->queryCounts($owner);

        foreach (['kadikoy', 'besiktas', 'uskudar'] as $slug) {
            $location = $this->location($this->tenant, $slug);
            $menu = $this->menu($this->tenant, "menu-{$slug}", [$location]);
            $item = $this->item($this->category($menu, 'Kategori'), 'Ürün');
            $this->qr($location, "QR {$slug}");
            $this->soldOut($location, $item);
        }

        $after = $this->queryCounts($owner);

        $this->assertSame($before, $after);

        foreach ($after as $widget => $count) {
            // PlanUsageWidget: one usage query per count feature (max_members added 2026-11-16).
            $this->assertLessThanOrEqual(13, $count, "{$widget} ran {$count} queries");
        }
    }
}
