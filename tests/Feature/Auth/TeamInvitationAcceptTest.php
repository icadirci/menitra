<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Mail\TeamInvitationMail;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Team\TeamInvitationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

class TeamInvitationAcceptTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private const string PASSWORD = 'a-Strong-passw0rd';

    private Tenant $tenant;

    private Location $moda;

    private Location $cihangir;

    private TenantMembership $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->tenant = $this->subscribedTenant('konak');
        $this->tenant->translations()->updateOrCreate(['locale' => 'tr'], ['name' => 'Kahve Konağı']);
        $this->tenant->unsetRelation('translations');
        $this->moda = $this->location($this->tenant, 'moda', true);
        $this->cihangir = $this->location($this->tenant, 'cihangir');
        $this->owner = TenantMembership::factory()->for($this->tenant)->create();
    }

    /**
     * Sends an invitation and returns the raw token from the queued mail.
     */
    private function invite(string $email = 'selin@example.com', TenantRole $role = TenantRole::Manager): string
    {
        Mail::fake();
        $this->inTenant($this->tenant, fn () => app(TeamInvitationService::class)
            ->invite($this->owner, $email, $role, [$this->moda->id]));

        $url = null;
        Mail::assertQueued(TeamInvitationMail::class, function (TeamInvitationMail $mail) use (&$url): bool {
            $url = $mail->url;

            return true;
        });

        return basename((string) parse_url((string) $url, PHP_URL_PATH));
    }

    private function invitation(): TenantInvitation
    {
        return $this->inTenant($this->tenant, fn () => TenantInvitation::query()->latest('id')->firstOrFail());
    }

    private function membershipOf(string $email): ?TenantMembership
    {
        return TenantMembership::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereHas('user', fn ($query) => $query->where('email', $email))
            ->first();
    }

    private function assertInvalidPage(string $token): void
    {
        $this->get("/invitations/{$token}")
            ->assertNotFound()
            ->assertSee(__('team.accept.invalid.title'))
            ->assertDontSee('Kahve Konağı')
            ->assertDontSee('selin@example.com');
    }

    public function test_new_user_creates_an_account_and_joins(): void
    {
        $token = $this->invite();

        $this->get("/invitations/{$token}")
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertSee(__('team.accept.title', ['business' => 'Kahve Konağı']))
            ->assertSee($this->moda->translate()?->name ?? 'moda')
            ->assertSee(__('team.accept.submit_new'));

        $this->post("/invitations/{$token}", [
            'name' => 'Selin Kaya',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'terms' => '1',
            // Never read: everything comes from the stored invitation.
            'email' => 'attacker@example.com',
            'role' => 'owner',
            'tenant_id' => 999,
            'location_ids' => [$this->cihangir->id],
        ])->assertRedirect('/app/konak');

        $user = User::query()->where('email', 'selin@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->platform_role);
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);

        $membership = $this->membershipOf('selin@example.com');
        $this->assertSame(TenantRole::Manager, $membership->role);
        $this->assertTrue($membership->is_active);
        $this->assertSame([$this->moda->id], $membership->assignedLocationIds()->pluck('location_id')->map(fn ($id) => (int) $id)->all());
        $this->assertNotNull($this->invitation()->accepted_at);

        $this->get('/app/konak')->assertOk();

        // Single use.
        auth()->logout();
        $this->assertInvalidPage($token);
    }

    public function test_new_user_must_accept_the_terms_and_the_version_is_recorded(): void
    {
        $token = $this->invite();

        $this->get("/invitations/{$token}")->assertOk()->assertSee('name="terms"', false);

        $this->from("/invitations/{$token}")
            ->post("/invitations/{$token}", ['name' => 'Selin', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertRedirect("/invitations/{$token}")
            ->assertSessionHasErrors('terms');
        $this->assertDatabaseMissing('users', ['email' => 'selin@example.com']);
        $this->assertTrue($this->invitation()->isPending());

        $this->post("/invitations/{$token}", ['name' => 'Selin', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'terms' => '1'])
            ->assertRedirect('/app/konak');

        $user = User::query()->where('email', 'selin@example.com')->sole();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(config('marketing.terms_version'), $user->terms_version);
    }

    public function test_new_user_password_follows_the_password_rules(): void
    {
        $token = $this->invite();

        $this->from("/invitations/{$token}")
            ->post("/invitations/{$token}", ['name' => 'Selin', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertRedirect("/invitations/{$token}")
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'selin@example.com']);
        $this->assertTrue($this->invitation()->isPending());
    }

    public function test_existing_account_accepts_with_its_password_without_signing_in(): void
    {
        $user = User::factory()->create(['email' => 'selin@example.com', 'password' => self::PASSWORD]);
        $token = $this->invite('Selin@Example.com');

        $this->get("/invitations/{$token}")->assertOk()->assertSee(__('team.accept.existing_account', ['email' => 'selin@example.com']));

        $this->from("/invitations/{$token}")
            ->post("/invitations/{$token}", ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');
        $this->assertNull($this->membershipOf('selin@example.com'));

        $this->post("/invitations/{$token}", ['password' => self::PASSWORD, 'name' => 'Ignored'])
            ->assertRedirect(route('invitations.accepted'));
        $this->assertGuest();
        $this->assertSame($user->name, $user->fresh()->name);
        $this->assertSame(TenantRole::Manager, $this->membershipOf('selin@example.com')->role);

        $this->get(route('invitations.accepted'))->assertOk()->assertSee('Kahve Konağı');
    }

    public function test_signed_in_user_with_the_invited_email_accepts_in_one_click(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'selin@example.com']);
        $token = $this->invite();

        $this->actingAs($user)->get("/invitations/{$token}")->assertOk()->assertSee(__('team.accept.submit'));
        $this->actingAs($user)->post("/invitations/{$token}")->assertRedirect('/app/konak');

        $this->assertNotNull($this->membershipOf('selin@example.com'));
        // The token proved the address.
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_signed_in_user_with_another_email_gets_an_error_and_no_membership(): void
    {
        $other = User::factory()->create(['email' => 'mert@example.com']);
        $token = $this->invite();

        $this->actingAs($other)->get("/invitations/{$token}")
            ->assertOk()
            ->assertSee(__('team.accept.errors.mismatch'))
            ->assertDontSee('selin@example.com');

        $this->actingAs($other)->from("/invitations/{$token}")->post("/invitations/{$token}")
            ->assertRedirect("/invitations/{$token}")
            ->assertSessionHasErrors('invitation');

        $this->assertSame(0, $other->memberships()->count());
        $this->assertTrue($this->invitation()->isPending());
    }

    public function test_unusable_tokens_show_one_generic_page(): void
    {
        $this->assertInvalidPage('unknown');
        $this->assertInvalidPage(str_repeat('A', 43));

        // Expired.
        $expired = $this->invite('expired@example.com');
        $this->travel(8)->days();
        $this->assertInvalidPage($expired);
        $this->post("/invitations/{$expired}", ['name' => 'X', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'expired@example.com']);
        $this->travelBack();

        // Revoked.
        $revoked = $this->invite('revoked@example.com');
        $this->inTenant($this->tenant, fn () => app(TeamInvitationService::class)->revoke($this->owner, $this->invitation()));
        $this->assertInvalidPage($revoked);
    }

    public function test_invitations_of_a_suspended_tenant_are_invalid(): void
    {
        $token = $this->invite();
        $this->tenant->forceFill(['status' => TenantStatus::Suspended])->save();

        $this->assertInvalidPage($token);
        $this->post("/invitations/{$token}", ['name' => 'Selin', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertNotFound();
        $this->assertNull($this->membershipOf('selin@example.com'));
    }

    public function test_an_existing_member_cannot_accept_twice(): void
    {
        $member = TenantMembership::factory()->for($this->tenant)->branchStaff()->inactive()->create();
        $token = $this->inTenant($this->tenant, function () use ($member): string {
            // Bypasses the "already a member" check of invite() to reach accept().
            return $this->inviteRaw($member->user->email);
        });

        $this->actingAs($member->user)->from("/invitations/{$token}")->post("/invitations/{$token}")
            ->assertSessionHasErrors('invitation');

        $this->assertSame(TenantRole::BranchStaff, $member->fresh()->role);
        $this->assertFalse($member->fresh()->is_active);
    }

    public function test_accepting_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'selin@example.com', 'password' => self::PASSWORD]);
        $token = $this->invite();

        for ($i = 0; $i < 5; $i++) {
            $this->post("/invitations/{$token}", ['password' => 'wrong'])->assertRedirect();
        }

        $this->post("/invitations/{$token}", ['password' => self::PASSWORD])->assertTooManyRequests();
        $this->assertNull($this->membershipOf('selin@example.com'));
    }

    public function test_invitation_routes_exist_only_on_the_central_domain(): void
    {
        $token = $this->invite();

        $this->get("http://konak.localhost/invitations/{$token}")->assertNotFound();
        $this->post("http://konak.localhost/invitations/{$token}", ['name' => 'Selin', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'selin@example.com']);
    }

    public function test_invitation_mail_renders_in_both_locales_with_escaped_names(): void
    {
        foreach (['tr', 'en'] as $locale) {
            $mail = (new TeamInvitationMail(
                url: 'http://localhost/invitations/'.str_repeat('a', 43),
                businessName: '<b>Konak</b>',
                inviterName: '<script>x</script>',
                role: TenantRole::BranchStaff,
                expiresAt: CarbonImmutable::now()->addDays(7),
            ))->locale($locale);

            $html = $mail->render();

            $this->assertStringContainsString('&lt;b&gt;Konak&lt;/b&gt;', $html);
            $this->assertStringNotContainsString('<script>x</script>', $html);
            $this->assertStringContainsString(__('team.mail.cta', [], $locale), $html);
            $this->assertStringContainsString(__('roles.tenant.branch_staff', [], $locale), $html);
            $mail->assertHasSubject(__('team.mail.subject', ['business' => '<b>Konak</b>'], $locale));
        }
    }

    /**
     * An open invitation written directly (test-only shortcut past invite()).
     */
    private function inviteRaw(string $email): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $invitation = new TenantInvitation;
        $invitation->forceFill([
            'email' => $email,
            'role' => TenantRole::Manager,
            'token_hash' => TenantInvitation::hashToken($token),
            'locale' => 'tr',
            'expires_at' => now()->addDay(),
            'sent_at' => now(),
        ])->save();
        $invitation->locations()->attach([$this->moda->id]);

        return $token;
    }
}
