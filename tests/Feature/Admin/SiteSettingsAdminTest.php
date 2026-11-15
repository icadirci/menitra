<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Admin\Pages\ManageSiteSettings;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * /admin site settings (platform contact details): super admins only.
 */
class SiteSettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    private function page(User $user): Testable
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');

        return Livewire::test(ManageSiteSettings::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function validForm(): array
    {
        return [
            'contact_email' => 'destek@restano.test',
            'phone' => '+90 212 555 01 23',
            'phone_has_whatsapp' => true,
            'instagram_url' => 'https://instagram.com/restano',
            'linkedin_url' => 'https://www.linkedin.com/company/restano',
            'translations' => [
                'tr' => ['address' => 'Moda Cd. 1, Kadıköy / İstanbul', 'support_hours' => 'Pzt–Cum, 09:00–18:00'],
                'en' => ['address' => '', 'support_hours' => 'Mon–Fri, 09:00–18:00'],
            ],
        ];
    }

    public function test_super_admin_opens_the_page_and_sees_the_navigation_entry(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin/site-settings')
            ->assertOk()
            ->assertSee(__('panels.admin.site_settings.title'));

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee(__('panels.admin.site_settings.navigation'));
    }

    public function test_platform_staff_is_forbidden_and_sees_no_entry(): void
    {
        $staff = User::factory()->platformStaff()->create();

        $this->actingAs($staff)->get('/admin/site-settings')->assertForbidden();
        $this->actingAs($staff)->get('/admin')
            ->assertOk()
            ->assertDontSee(__('panels.admin.site_settings.navigation'));
    }

    public function test_business_users_and_guests_cannot_reach_the_page(): void
    {
        $owner = TenantMembership::factory()->for(Tenant::factory())->create()->user;

        $this->actingAs($owner)->get('/admin/site-settings')->assertForbidden();

        auth()->logout();
        $this->get('/admin/site-settings')->assertRedirect('/admin/login');
    }

    public function test_livewire_requests_are_reauthorized(): void
    {
        $this->page(User::factory()->platformStaff()->create())->assertForbidden();
    }

    public function test_super_admin_saves_contact_details_and_translations(): void
    {
        $this->page(User::factory()->superAdmin()->create())
            ->fillForm($this->validForm())
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('panels.admin.site_settings.saved'));

        $settings = SiteSetting::query()->with('translations')->sole();
        $this->assertSame(SiteSetting::SINGLETON_ID, $settings->id);
        $this->assertSame('destek@restano.test', $settings->contact_email);
        $this->assertSame('+90 212 555 01 23', $settings->phone);
        $this->assertTrue($settings->phone_has_whatsapp);
        $this->assertSame('https://instagram.com/restano', $settings->instagram_url);
        $this->assertNull($settings->facebook_url);
        $this->assertSame('Pzt–Cum, 09:00–18:00', $settings->translate('tr')->support_hours);
        $this->assertSame('Mon–Fri, 09:00–18:00', $settings->translate('en')->support_hours);
        $this->assertNull($settings->translate('en')->address);

        // The public pages show the saved values.
        $this->get('/iletisim')->assertSee('Moda Cd. 1, Kadıköy / İstanbul')->assertSee('href="https://wa.me/902125550123"', escape: false);
    }

    public function test_the_form_is_filled_from_the_saved_settings_and_fields_can_be_cleared(): void
    {
        $settings = SiteSetting::current();
        $settings->update(['contact_email' => 'eski@restano.test', 'youtube_url' => 'https://youtube.com/@restano']);
        $settings->translations()->create(['locale' => 'en', 'support_hours' => '24/7']);

        $this->page(User::factory()->superAdmin()->create())
            ->assertSchemaStateSet([
                'contact_email' => 'eski@restano.test',
                'youtube_url' => 'https://youtube.com/@restano',
                'translations.en.support_hours' => '24/7',
            ], 'form')
            ->fillForm(['youtube_url' => null, 'translations' => ['tr' => ['address' => null, 'support_hours' => null], 'en' => ['address' => null, 'support_hours' => null]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings->refresh()->load('translations');
        $this->assertNull($settings->youtube_url);
        $this->assertSame('eski@restano.test', $settings->contact_email);
        $this->assertNull($settings->translations->firstWhere('locale', 'en'), 'An emptied non-default locale is removed.');
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->page(User::factory()->superAdmin()->create())
            ->fillForm([
                ...$this->validForm(),
                'contact_email' => 'not-an-email',
                'phone' => 'call me',
                'instagram_url' => 'http://instagram.com/restano',
                'facebook_url' => 'javascript:alert(1)',
            ])
            ->call('save')
            ->assertHasFormErrors(['contact_email', 'phone', 'instagram_url', 'facebook_url']);

        $this->assertNull(SiteSetting::query()->first()?->contact_email);
    }

    public function test_save_is_reauthorized_on_the_server(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $page = $this->page($admin)->fillForm($this->validForm());

        $admin->forceFill(['platform_role' => null])->save();

        $page->call('save')->assertForbidden();
        $this->assertNull(SiteSetting::query()->first()?->contact_email);
    }

    public function test_only_one_settings_row_can_exist(): void
    {
        SiteSetting::current();
        $this->assertSame(SiteSetting::current()->id, SiteSetting::current()->id);

        $this->expectException(QueryException::class);
        SiteSetting::query()->insert(['id' => 2, 'created_at' => now(), 'updated_at' => now()]);
    }
}
