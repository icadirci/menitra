<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_loads(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee(__('auth.forgot.title'));
    }

    public function test_full_password_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->from('/forgot-password')
            ->followingRedirects()
            ->post('/forgot-password', ['email' => 'owner@example.com'])
            ->assertOk()
            ->assertSee(__('auth.forgot.sent_title'))
            ->assertDontSee(__('auth.forgot.submit'));

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->get("/reset-password/{$token}?email=owner@example.com")
            ->assertOk()
            ->assertSee(__('auth.reset.title'));

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'owner@example.com',
            'password' => 'a-New-passw0rd!',
            'password_confirmation' => 'a-New-passw0rd!',
        ])->assertRedirect('/login')->assertSessionHas('status');

        $this->assertTrue(Hash::check('a-New-passw0rd!', $user->fresh()->password));
        $this->get('/login')->assertRedirect('/app/login');
    }

    public function test_invalid_token_does_not_reset_the_password(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'owner@example.com',
            'password' => 'a-New-passw0rd!',
            'password_confirmation' => 'a-New-passw0rd!',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_unknown_email_sends_nothing(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        Notification::assertNothingSent();
    }

    public function test_password_reset_requests_are_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/forgot-password', ['email' => 'nobody@example.com']);
        }

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertStatus(429);
    }

    public function test_there_is_no_second_login_endpoint(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->get('/login')->assertRedirect('/app/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(405);
        $this->assertGuest();
    }
}
