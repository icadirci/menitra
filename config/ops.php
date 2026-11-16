<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Operational health check (`php artisan ops:health-check`)
|--------------------------------------------------------------------------
|
| Scheduled every 15 minutes (routes/console.php). Problems are mailed to
| the alert address; the same check alerts at most once per cooldown.
| See README § Monitoring.
|
*/

return [

    // Falls back to the platform contact e-mail (/admin → site settings).
    'alert_email' => env('OPS_ALERT_EMAIL'),

    'alert_cooldown_minutes' => (int) env('OPS_ALERT_COOLDOWN_MINUTES', 60),

    // Alert when at least this many jobs failed since the last alert.
    'failed_jobs_threshold' => (int) env('OPS_FAILED_JOBS_THRESHOLD', 1),

    // Used space of the disk holding storage/ (uploads, logs, backups).
    'disk_usage_threshold_percent' => (int) env('OPS_DISK_USAGE_THRESHOLD', 85),

    // Off until backups exist (docs/launch-plan.md, Faz 4).
    'backup' => [
        'enabled' => (bool) env('OPS_BACKUP_CHECK', false),
        'path' => env('OPS_BACKUP_PATH', storage_path('app/private/backups')),
        'max_age_hours' => (int) env('OPS_BACKUP_MAX_AGE_HOURS', 26),
    ],

];
