<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\TenantMembership;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => $hash ?? sha1($user->getEmailForVerification()),
        ]);
    }

    public function test_verification_notice_is_shown_to_unverified_users(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/email/verify')->assertOk()->assertSee(__('auth.verify.title'));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/email/verify')->assertRedirect('http://localhost/login');
    }

    public function test_signed_link_verifies_the_email(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->verificationUrl($user))->assertRedirect('/app?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_link_with_wrong_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->verificationUrl($user, sha1('someone-else@example.com')))->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_of_another_user_is_rejected(): void
    {
        $victim = User::factory()->unverified()->create();
        $attacker = User::factory()->unverified()->create();

        $this->actingAs($attacker)->get($this->verificationUrl($victim))->assertForbidden();

        $this->assertFalse($victim->fresh()->hasVerifiedEmail());
    }

    public function test_unsigned_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/email/verify/'.$user->getKey().'/'.sha1($user->getEmailForVerification()))
            ->assertForbidden();
    }

    private function unverifiedOwner(): TenantMembership
    {
        $membership = TenantMembership::factory()->create();
        $membership->user->forceFill(['email_verified_at' => null])->save();

        return $membership;
    }

    public function test_unverified_member_is_sent_to_the_verification_notice_instead_of_the_panel(): void
    {
        $membership = $this->unverifiedOwner();

        $this->actingAs($membership->user)->get('/app')->assertRedirect('http://localhost/email/verify');
        $this->actingAs($membership->user)->get("/app/{$membership->tenant->slug}")->assertRedirect('http://localhost/email/verify');
        $this->actingAs($membership->user)->get('/email/verify')->assertOk();
    }

    public function test_unverified_member_can_log_in_and_is_then_asked_to_verify(): void
    {
        $membership = $this->unverifiedOwner();
        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm(['email' => $membership->user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->get("/app/{$membership->tenant->slug}")->assertRedirect('http://localhost/email/verify');
    }

    public function test_verifying_unlocks_the_business_panel(): void
    {
        $membership = $this->unverifiedOwner();
        $user = $membership->user;

        $this->actingAs($user)->get($this->verificationUrl($user))->assertRedirect('/app?verified=1');

        $this->actingAs($user->fresh())->get("/app/{$membership->tenant->slug}")->assertOk();
    }

    public function test_unverified_user_without_membership_still_gets_403_from_the_business_panel(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->get('/app')->assertForbidden();
    }

    public function test_admin_panel_does_not_require_a_verified_email(): void
    {
        $this->actingAs(User::factory()->unverified()->superAdmin()->create())->get('/admin')->assertOk();
    }

    public function test_unverified_user_can_log_out_from_the_verification_page(): void
    {
        $membership = $this->unverifiedOwner();

        $this->actingAs($membership->user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_logout_requires_a_signed_in_user(): void
    {
        $this->post('/logout')->assertRedirect('http://localhost/login');
    }

    public function test_verification_email_can_be_resent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->from('/email/verify')
            ->post('/email/verification-notification')
            ->assertRedirect('/email/verify')
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
