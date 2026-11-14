<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
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
