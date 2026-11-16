<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Launch plan Faz 3: every platform mail goes through the queue, and the
 * account mails (verification, password reset) are branded and localised.
 */
class AccountMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_mailable_and_notification_is_queued(): void
    {
        $classes = collect([app_path('Mail'), app_path('Notifications')])
            ->filter(fn (string $dir): bool => File::isDirectory($dir))
            ->flatMap(fn (string $dir) => File::allFiles($dir))
            ->map(fn ($file): string => 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen(app_path()) + 1)));

        $this->assertNotEmpty($classes);

        foreach ($classes as $class) {
            if (is_subclass_of($class, Mailable::class) || is_subclass_of($class, BaseNotification::class)) {
                $this->assertTrue(is_subclass_of($class, ShouldQueue::class), "{$class} must implement ShouldQueue.");
            }
        }
    }

    public function test_verification_mail_is_queued_in_the_request_locale(): void
    {
        Queue::fake();
        $user = User::factory()->unverified()->create();

        app()->setLocale('en');
        $user->sendEmailVerificationNotification();

        Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job): bool {
            return $job->notification instanceof VerifyEmailNotification && $job->notification->locale === 'en';
        });
    }

    public function test_password_reset_mail_is_queued_in_the_request_locale(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $user->sendPasswordResetNotification('token-123');

        Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job): bool {
            return $job->notification instanceof ResetPasswordNotification && $job->notification->locale === 'tr';
        });
    }

    public function test_verification_mail_renders_branded_in_turkish_and_english_with_escaped_name(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Ayşe <b>Yaman</b>']);

        $tr = $this->render(new VerifyEmailNotification, $user, 'tr');
        $this->assertSame('E-posta adresinizi doğrulayın', $tr['subject']);
        $this->assertStringContainsString('Restano', $tr['html']);
        $this->assertStringContainsString('E-posta adresimi doğrula', $tr['html']);
        $this->assertStringContainsString('Ayşe &lt;b&gt;Yaman&lt;/b&gt;', $tr['html']);
        $this->assertStringNotContainsString('<b>Yaman</b>', $tr['html']);
        $this->assertStringContainsString('/email/verify/'.$user->getKey().'/', $tr['text']);
        $this->assertStringNotContainsString('Laravel', $tr['html']);

        $en = $this->render(new VerifyEmailNotification, $user, 'en');
        $this->assertSame('Verify your email address', $en['subject']);
        $this->assertStringContainsString('Verify my email address', $en['text']);
    }

    public function test_password_reset_mail_renders_branded_in_turkish_and_english(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $tr = $this->render(new ResetPasswordNotification('token-123'), $user, 'tr');
        $this->assertSame('Şifre sıfırlama bağlantınız', $tr['subject']);
        $this->assertStringContainsString('Yeni şifre belirle', $tr['html']);
        $this->assertStringContainsString('/reset-password/token-123?email=owner%40example.com', $tr['text']);
        $this->assertStringContainsString('60 dakika', $tr['text']);
        $this->assertStringNotContainsString('Laravel', $tr['html']);

        $en = $this->render(new ResetPasswordNotification('token-123'), $user, 'en');
        $this->assertSame('Your password reset link', $en['subject']);
        $this->assertStringContainsString('Choose a new password', $en['html']);
    }

    public function test_tr_and_en_mail_strings_have_the_same_keys(): void
    {
        $keys = fn (string $locale): array => array_keys(Arr::dot((require lang_path("{$locale}/auth.php"))['mail']));

        $this->assertSame($keys('tr'), $keys('en'));
    }

    /**
     * @return array{subject: string, html: string, text: string}
     */
    private function render(BaseNotification $notification, User $user, string $locale): array
    {
        app()->setLocale($locale);
        $message = $notification->toMail($user);
        [$html, $text] = $message->view;

        return [
            'subject' => $message->subject,
            'html' => view($html, $message->viewData)->render(),
            'text' => view($text, $message->viewData)->render(),
        ];
    }
}
