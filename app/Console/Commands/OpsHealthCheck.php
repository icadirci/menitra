<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\OpsAlertMail;
use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Every 15 minutes (routes/console.php): failed queue jobs since the last
 * alert, disk usage of storage/, age of the newest backup (config/ops.php).
 *
 * Problems are mailed synchronously (`sendNow`), because a broken queue is
 * one of the things being checked. Each check alerts at most once per
 * cooldown; a failed job is reported once, not on every run. Exits non-zero
 * while any check fails. Platform-level: touches no tenant data.
 */
class OpsHealthCheck extends Command
{
    protected $signature = 'ops:health-check';

    protected $description = 'Check failed jobs, disk usage and backups; mail new problems';

    private const string ALERTED_KEY = 'ops:health:alerted:';

    private const string FAILED_JOBS_SEEN_KEY = 'ops:health:failed-jobs:last-id';

    private int $failedJobsMaxId = 0;

    public function handle(): int
    {
        $checks = [
            'failed_jobs' => $this->checkFailedJobs(),
            'disk' => $this->checkDisk(),
            'backup' => $this->checkBackup(),
        ];

        $this->table(
            [__('ops.table.check'), __('ops.table.status')],
            collect($checks)->map(fn (?string $problem, string $key): array => [
                __("ops.checks.{$key}"),
                match (true) {
                    $key === 'backup' && ! config('ops.backup.enabled') => __('ops.status.disabled'),
                    $problem === null => __('ops.status.ok'),
                    default => $problem,
                },
            ])->values()->all(),
        );

        $problems = array_filter($checks);

        if ($problems === []) {
            return self::SUCCESS;
        }

        $this->notifyTeam($problems);

        return self::FAILURE;
    }

    private function checkFailedJobs(): ?string
    {
        if (! in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true)) {
            return null;
        }

        $query = DB::connection(config('queue.failed.database'))
            ->table(config('queue.failed.table'))
            ->where('id', '>', (int) Cache::get(self::FAILED_JOBS_SEEN_KEY, 0));

        $count = $query->count();

        if ($count < max(1, (int) config('ops.failed_jobs_threshold'))) {
            return null;
        }

        $this->failedJobsMaxId = (int) $query->max('id');

        return __('ops.problems.failed_jobs', ['count' => $count]);
    }

    private function checkDisk(): ?string
    {
        $total = @disk_total_space(storage_path());
        $free = @disk_free_space(storage_path());

        if (! $total || $free === false) {
            return __('ops.problems.disk_unreadable');
        }

        $used = (int) round((1 - $free / $total) * 100);

        if ($used < (int) config('ops.disk_usage_threshold_percent')) {
            return null;
        }

        return __('ops.problems.disk', ['percent' => $used, 'free' => round($free / 1024 ** 3, 1)]);
    }

    private function checkBackup(): ?string
    {
        if (! config('ops.backup.enabled')) {
            return null;
        }

        $path = (string) config('ops.backup.path');
        $newest = File::isDirectory($path)
            ? collect(File::allFiles($path))->max(fn ($file): int => $file->getMTime())
            : null;

        if ($newest === null) {
            return __('ops.problems.backup_missing');
        }

        $hours = (int) floor((time() - $newest) / 3600);

        if ($hours < (int) config('ops.backup.max_age_hours')) {
            return null;
        }

        return __('ops.problems.backup_old', ['hours' => $hours]);
    }

    /**
     * @param  array<string, string>  $problems
     */
    private function notifyTeam(array $problems): void
    {
        $cooldown = now()->addMinutes(max(1, (int) config('ops.alert_cooldown_minutes')));
        $due = array_filter(
            $problems,
            fn (string $key): bool => Cache::add(self::ALERTED_KEY.$key, true, $cooldown),
            ARRAY_FILTER_USE_KEY,
        );

        if ($due === []) {
            $this->line(__('ops.output.cooldown'));

            return;
        }

        Log::error('Health check failed.', $due);

        $recipient = $this->recipient();

        if ($recipient === null) {
            Log::critical('Health check problems found, but no alert e-mail is configured (OPS_ALERT_EMAIL or site settings).');
            $this->error(__('ops.output.no_recipient'));

            return;
        }

        try {
            Mail::to($recipient)->sendNow(new OpsAlertMail(array_values($due)));
        } catch (Throwable $exception) {
            // Retry on the next run instead of staying silent for a cooldown.
            foreach (array_keys($due) as $key) {
                Cache::forget(self::ALERTED_KEY.$key);
            }

            report($exception);
            $this->error(__('ops.output.mail_failed'));

            return;
        }

        if (isset($due['failed_jobs'])) {
            Cache::forever(self::FAILED_JOBS_SEEN_KEY, $this->failedJobsMaxId);
        }

        $this->info(__('ops.output.sent', ['email' => $recipient]));
    }

    private function recipient(): ?string
    {
        $email = config('ops.alert_email') ?: SiteSetting::current()->contact_email;

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
