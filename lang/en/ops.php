<?php

declare(strict_types=1);

return [

    'checks' => [
        'failed_jobs' => 'Failed queue jobs',
        'disk' => 'Disk usage',
        'backup' => 'Latest backup',
    ],

    'table' => [
        'check' => 'Check',
        'status' => 'Status',
    ],

    'status' => [
        'ok' => 'OK',
        'disabled' => 'Disabled',
    ],

    'problems' => [
        'failed_jobs' => ':count queue jobs failed since the last alert (php artisan queue:failed).',
        'disk' => 'Disk is :percent% full, :free GB free.',
        'disk_unreadable' => 'Disk usage could not be read.',
        'backup_missing' => 'No backup file found.',
        'backup_old' => 'The latest backup is :hours hours old.',
    ],

    'output' => [
        'cooldown' => 'An alert for these problems was sent recently.',
        'no_recipient' => 'No alert e-mail configured (OPS_ALERT_EMAIL or site settings).',
        'mail_failed' => 'The alert e-mail could not be sent; the next run retries.',
        'sent' => 'Alert sent to :email.',
    ],

    'mail' => [
        'subject' => '[:app · :env] :count health check problem(s)',
        'heading' => 'Health check problems',
        'footer' => 'The same check alerts at most once every :minutes minutes.',
    ],

];
