<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Tests\TestCase;

/**
 * Drives Livewire's HTTP endpoints the way its JavaScript client does, using
 * real component snapshots taken from rendered panel pages.
 */
class LivewireEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT_HOST = 'http://acme.localhost';

    private function snapshotFrom(TestResponse $page): string
    {
        preg_match('/wire:snapshot="([^"]+)"/', $page->getContent(), $match);
        $this->assertNotEmpty($match, 'The page rendered no Livewire component.');

        return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
    }

    private function update(string $snapshot, string $base = 'http://localhost', ?string $path = null): TestResponse
    {
        // Each PHP-FPM request starts with fresh Livewire state; in one PHPUnit
        // process it would leak between requests (e.g. the "middleware already
        // applied for this route" marker), so reset it like a new request.
        Livewire::flushState();

        return $this->postJson($base.($path ?? route('panels.livewire.update', absolute: false)), [
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]],
        ], ['X-Livewire' => '1']);
    }

    public function test_panel_pages_point_livewire_at_the_central_update_route(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('http://localhost'.route('panels.livewire.update', absolute: false), escape: false);
    }

    public function test_update_works_on_the_central_domain(): void
    {
        $snapshot = $this->snapshotFrom($this->get('/admin/login'));

        $this->update($snapshot)->assertOk()->assertJsonStructure(['components' => [['snapshot', 'effects']]]);
    }

    public function test_update_is_refused_on_tenant_hosts(): void
    {
        $snapshot = $this->snapshotFrom($this->get('/admin/login'));

        $this->update($snapshot, self::TENANT_HOST)->assertNotFound();
    }

    public function test_default_host_agnostic_update_route_is_disabled_everywhere(): void
    {
        $snapshot = $this->snapshotFrom($this->get('/admin/login'));

        $this->update($snapshot, 'http://localhost', EndpointResolver::updatePath())->assertNotFound();
        $this->update($snapshot, self::TENANT_HOST, EndpointResolver::updatePath())->assertNotFound();
    }

    public function test_admin_panel_authorization_is_rechecked_on_every_update(): void
    {
        $admin = User::factory()->platformStaff()->create();
        $snapshot = $this->snapshotFrom($this->actingAs($admin)->get('/admin')->assertOk());

        $this->update($snapshot)->assertOk();

        $admin->forceFill(['platform_role' => null])->save();

        $this->update($snapshot)->assertForbidden();
    }

    public function test_tenant_membership_is_rechecked_on_every_update(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $membershipA = TenantMembership::factory()->for($tenantA)->create();
        $membershipB = TenantMembership::factory()->for($membershipA->user)->for($tenantB)->create();

        $snapshot = $this->snapshotFrom($this->actingAs($membershipA->user)->get("/app/{$tenantB->slug}")->assertOk());
        $this->update($snapshot)->assertOk();

        // Still a business user (tenant A), but no longer a member of B.
        $membershipB->forceFill(['is_active' => false])->save();

        $this->update($snapshot)->assertNotFound();
    }

    public function test_update_from_a_suspended_tenant_page_is_refused(): void
    {
        $membership = TenantMembership::factory()->create();
        $snapshot = $this->snapshotFrom($this->actingAs($membership->user)->get("/app/{$membership->tenant->slug}")->assertOk());

        $membership->tenant->forceFill(['status' => 'suspended'])->save();

        $this->update($snapshot)->assertForbidden();
    }

    private function signedUploadUrl(string $base = 'http://localhost'): string
    {
        return $base.URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), absolute: false);
    }

    private function upload(string $url): TestResponse
    {
        return $this->post($url, ['files' => [UploadedFile::fake()->image('logo.png', 64, 64)]], ['Accept' => 'application/json']);
    }

    public function test_panel_user_can_upload_temporary_files_on_the_central_domain(): void
    {
        Storage::fake('tmp-for-tests'); // Livewire's temporary upload disk under PHPUnit

        $this->actingAs(User::factory()->superAdmin()->create());

        $this->upload($this->signedUploadUrl())->assertOk()->assertJsonStructure(['paths']);
    }

    public function test_upload_is_refused_on_tenant_hosts(): void
    {
        Storage::fake('tmp-for-tests'); // Livewire's temporary upload disk under PHPUnit

        $this->actingAs(User::factory()->superAdmin()->create());

        $this->upload($this->signedUploadUrl(self::TENANT_HOST))->assertNotFound();
    }

    public function test_upload_is_refused_for_guests(): void
    {
        Storage::fake('tmp-for-tests'); // Livewire's temporary upload disk under PHPUnit

        $this->upload($this->signedUploadUrl())->assertForbidden();
    }

    public function test_upload_is_refused_for_users_without_panel_access(): void
    {
        Storage::fake('tmp-for-tests'); // Livewire's temporary upload disk under PHPUnit

        $this->actingAs(User::factory()->create());

        $this->upload($this->signedUploadUrl())->assertForbidden();
    }

    public function test_upload_still_requires_livewires_signed_url(): void
    {
        Storage::fake('tmp-for-tests'); // Livewire's temporary upload disk under PHPUnit

        $this->actingAs(User::factory()->superAdmin()->create());

        $this->upload('http://localhost'.route('livewire.upload-file', absolute: false))->assertUnauthorized();
    }

    public function test_preview_is_refused_on_tenant_hosts_and_for_guests(): void
    {
        $path = URL::temporarySignedRoute('livewire.preview-file', now()->addMinutes(5), ['filename' => 'x.png'], absolute: false);

        $this->get(self::TENANT_HOST.$path)->assertNotFound();
        $this->get('http://localhost'.$path)->assertForbidden();
    }
}
