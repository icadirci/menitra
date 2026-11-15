<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\MediaType;
use App\Enums\PlatformRole;
use App\Filament\App\Pages\EditBusinessProfile;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenancy\TenantLocaleService;
use App\Support\Media\TenantMediaPath;
use App\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessProfilePageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');

        $this->tenant = Tenant::factory()->create(['slug' => 'yaman-kahvesi']);
        $this->tenant->translations()->where('locale', 'tr')->update(['name' => 'Yaman Kahvesi']);
    }

    private function member(string $role = 'owner', ?Tenant $tenant = null): User
    {
        $factory = TenantMembership::factory()->for($tenant ?? $this->tenant);
        $membership = ($role === 'owner' ? $factory : $factory->{$role}())->create();

        if ($role !== 'owner') {
            $membership->locations()->attach(Location::factory()->for($tenant ?? $this->tenant)->create());
        }

        return $membership->user;
    }

    private function page(User $user): Testable
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->tenant);
        $this->app->make(TenantContext::class)->set($this->tenant);

        return Livewire::test(EditBusinessProfile::class);
    }

    public function test_owner_sees_the_profile(): void
    {
        $this->actingAs($this->member())
            ->get('/app/yaman-kahvesi/business-profile')
            ->assertOk()
            ->assertSee(__('panels.app.profile.title'))
            ->assertSee('yaman-kahvesi');
    }

    public function test_other_roles_are_forbidden_and_outsiders_get_404(): void
    {
        foreach (['manager', 'branchStaff'] as $role) {
            $this->actingAs($this->member($role))->get('/app/yaman-kahvesi/business-profile')->assertForbidden();
        }

        $this->actingAs($this->member('owner', Tenant::factory()->create()))
            ->get('/app/yaman-kahvesi/business-profile')
            ->assertNotFound();

        $admin = User::factory()->create();
        $admin->forceFill(['platform_role' => PlatformRole::SuperAdmin])->save();
        $this->actingAs($admin)->get('/app/yaman-kahvesi/business-profile')->assertForbidden();
    }

    public function test_livewire_requests_are_reauthorized(): void
    {
        $this->page($this->member('manager'))->assertForbidden();
    }

    public function test_owner_updates_translations_without_touching_disabled_locales(): void
    {
        $this->tenant->translations()->create(['locale' => 'de', 'name' => 'Yaman Kaffee']);

        $this->page($this->member())
            ->assertSet('data.translations.tr.name', 'Yaman Kahvesi')
            ->set('data.translations.tr.name', 'Yaman Kahve Evi')
            ->set('data.translations.tr.tagline', 'Odun ateşinde kahve')
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified(__('panels.app.profile.saved'));

        $translations = $this->tenant->translations()->get()->keyBy('locale');
        $this->assertSame('Yaman Kahve Evi', $translations['tr']->name);
        $this->assertSame('Odun ateşinde kahve', $translations['tr']->tagline);
        // `de` isn't enabled: its row is kept untouched.
        $this->assertSame('Yaman Kaffee', $translations['de']->name);
    }

    public function test_the_default_locale_name_is_required(): void
    {
        $this->page($this->member())
            ->set('data.translations.tr.name', '')
            ->call('save')
            ->assertHasErrors(['data.translations.tr.name']);

        $this->assertSame('Yaman Kahvesi', $this->tenant->translations()->where('locale', 'tr')->value('name'));
    }

    public function test_an_enabled_second_locale_gets_its_own_tab(): void
    {
        config(['locales.supported' => ['tr' => 'Türkçe', 'en' => 'English']]);
        app(SubscriptionService::class)->startTrial($this->tenant, Plan::factory()->withLimits(['max_languages' => 2])->create());
        $this->app->make(TenantContext::class)->set($this->tenant);
        app(TenantLocaleService::class)->enable('en');

        $this->page($this->member())
            ->set('data.translations.en.name', 'Yaman Coffee')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Yaman Coffee', $this->tenant->translations()->where('locale', 'en')->value('name'));
    }

    public function test_owner_uploads_a_logo_into_the_tenant_directory(): void
    {
        $this->page($this->member())
            ->set('data.logo_path', UploadedFile::fake()->image('logo.png', 300, 300))
            ->call('save')
            ->assertHasNoErrors();

        $path = $this->tenant->refresh()->logo_path;
        $this->assertNotNull($path);
        $this->assertTrue(TenantMediaPath::isOwnedBy($this->tenant, $path, MediaType::Logo));
        Storage::disk('media')->assertExists($path);
    }

    public function test_an_svg_logo_is_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->page($this->member())
            ->set('data.logo_path', $svg)
            ->call('save')
            ->assertHasErrors(['data.logo_path']);

        $this->assertNull($this->tenant->refresh()->logo_path);
    }
}
