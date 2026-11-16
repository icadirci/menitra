<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Mail\OpsAlertMail;
use App\Models\SiteSetting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ops:health-check (launch plan Faz 5): thresholds, one alert per cooldown,
 * each failed job reported once, recipient fallback, synchronous sending.
 */
class HealthCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $backupPath;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->backupPath = storage_path('framework/testing/backups-'.Str::random(8));

        config([
            'ops.alert_email' => 'ops@example.test',
            'ops.alert_cooldown_minutes' => 60,
            'ops.failed_jobs_threshold' => 1,
            // Never triggered by the real disk unless a test lowers it.
            'ops.disk_usage_threshold_percent' => 101,
            'ops.backup.enabled' => false,
            'ops.backup.path' => $this->backupPath,
            'ops.backup.max_age_hours' => 26,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupPath);

        parent::tearDown();
    }

    public function test_a_healthy_system_exits_zero_and_sends_nothing(): void
    {
        $this->artisan('ops:health-check')->assertSuccessful();

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_failed_jobs_are_alerted_once_each(): void
    {
        $this->failJobs(2);

        $this->artisan('ops:health-check')->assertFailed();
        Mail::assertSent(OpsAlertMail::class, fn (OpsAlertMail $mail): bool => $mail->hasTo('ops@example.test')
            && str_contains($mail->problems[0], '2'));
        Mail::assertNothingQueued();

        // Reported failures count as seen.
        $this->artisan('ops:health-check')->assertSuccessful();
        Mail::assertSentCount(1);

        // A new failure within the cooldown waits for it to pass.
        $this->failJobs(1);
        $this->artisan('ops:health-check')->assertFailed();
        Mail::assertSentCount(1);

        $this->travel(61)->minutes();
        $this->artisan('ops:health-check')->assertFailed();
        Mail::assertSentCount(2);
        Mail::assertSent(OpsAlertMail::class, fn (OpsAlertMail $mail): bool => $mail->problems === [__('ops.problems.failed_jobs', ['count' => 1])]);
    }

    public function test_the_failed_jobs_threshold_applies(): void
    {
        config(['ops.failed_jobs_threshold' => 3]);
        $this->failJobs(2);

        $this->artisan('ops:health-check')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_a_persistent_problem_alerts_once_per_cooldown(): void
    {
        config(['ops.disk_usage_threshold_percent' => 0]);

        $this->artisan('ops:health-check')->assertFailed();
        $this->artisan('ops:health-check')->assertFailed();
        Mail::assertSentCount(1);

        $this->travel(61)->minutes();
        $this->artisan('ops:health-check')->assertFailed();
        Mail::assertSentCount(2);
    }

    public function test_the_backup_check_is_off_by_default(): void
    {
        $this->artisan('ops:health-check')
            ->expectsOutputToContain(__('ops.status.disabled'))
            ->assertSuccessful();
    }

    public function test_a_missing_backup_is_a_problem_when_enabled(): void
    {
        config(['ops.backup.enabled' => true]);

        $this->artisan('ops:health-check')->assertFailed();
        Mail::assertSent(OpsAlertMail::class, fn (OpsAlertMail $mail): bool => $mail->problems === [__('ops.problems.backup_missing')]);
    }

    public function test_a_fresh_backup_passes_and_an_old_one_fails(): void
    {
        config(['ops.backup.enabled' => true]);
        File::ensureDirectoryExists($this->backupPath.'/daily');
        $file = $this->backupPath.'/daily/restano.dump';
        File::put($file, 'dump');

        $this->artisan('ops:health-check')->assertSuccessful();

        touch($file, time() - 27 * 3600);
        clearstatcache();

        $this->artisan('ops:health-check')->assertFailed();
        Mail::assertSent(OpsAlertMail::class, fn (OpsAlertMail $mail): bool => $mail->problems === [__('ops.problems.backup_old', ['hours' => 27])]);
    }

    public function test_the_recipient_falls_back_to_the_platform_contact_email(): void
    {
        config(['ops.alert_email' => null, 'ops.disk_usage_threshold_percent' => 0]);
        SiteSetting::current()->update(['contact_email' => 'hello@restano.test']);

        $this->artisan('ops:health-check')->assertFailed();

        Mail::assertSent(OpsAlertMail::class, fn (OpsAlertMail $mail): bool => $mail->hasTo('hello@restano.test'));
    }

    public function test_without_a_recipient_nothing_is_sent(): void
    {
        config(['ops.alert_email' => null, 'ops.disk_usage_threshold_percent' => 0]);

        $this->artisan('ops:health-check')
            ->expectsOutputToContain(__('ops.output.no_recipient'))
            ->assertFailed();

        Mail::assertNothingSent();
    }

    public function test_a_failed_mail_is_retried_on_the_next_run(): void
    {
        config(['ops.disk_usage_threshold_percent' => 0]);
        // Real mailer pointed at a closed port: the transport throws.
        Mail::swap(new MailManager($this->app));
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

        $this->artisan('ops:health-check')
            ->expectsOutputToContain(__('ops.output.mail_failed'))
            ->assertFailed();

        Mail::fake();
        $this->artisan('ops:health-check')->assertFailed();
        Mail::assertSentCount(1);
    }

    public function test_it_is_scheduled_every_fifteen_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'ops:health-check'));

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    public function test_the_alert_mail_renders_escaped_in_both_formats(): void
    {
        $mail = new OpsAlertMail(['Disk <b>full</b>']);

        $mail->assertSeeInHtml('Disk &lt;b&gt;full&lt;/b&gt;', false);
        $mail->assertSeeInText('Disk &lt;b&gt;full&lt;/b&gt;', false);

        app()->setLocale('en');
        $mail->assertHasSubject('['.config('app.name').' · testing] 1 health check problem(s)');
    }

    private function failJobs(int $count): void
    {
        foreach (range(1, $count) as $ignored) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'RuntimeException: test',
                'failed_at' => now(),
            ]);
        }
    }
}
