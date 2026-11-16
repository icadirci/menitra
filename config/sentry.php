<?php

declare(strict_types=1);

use App\Support\Monitoring\SentryEventScrubber;

/*
|--------------------------------------------------------------------------
| Sentry (error tracking)
|--------------------------------------------------------------------------
|
| Errors only: no tracing, profiling, logs or metrics. Without a DSN the SDK
| sends nothing (local, testing). Personal data never leaves the server:
| `data_collection` is explicit, so the SDK's permissive defaults for that
| option (user info, request bodies, stack frame variables) don't apply.
| Token-bearing URL paths are redacted by SentryEventScrubber.
| See README § Monitoring and .claude/rules/security.md.
|
*/

return [

    'dsn' => env('SENTRY_LARAVEL_DSN'),

    // e.g. the deployed git commit; optional.
    'release' => env('SENTRY_RELEASE'),

    // Defaults to APP_ENV.
    'environment' => env('SENTRY_ENVIRONMENT'),

    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 1.0),

    // Performance monitoring and profiling are off.
    'traces_sample_rate' => null,
    'profiles_sample_rate' => null,

    'enable_logs' => false,
    'enable_metrics' => false,

    // Legacy switch, kept false for clarity; `data_collection` below wins.
    'send_default_pii' => false,

    'data_collection' => [
        // No user id, e-mail or IP address.
        'user_info' => false,
        // Session and CSRF cookies are never sent.
        'cookies' => ['mode' => 'off'],
        'http_headers' => [
            'request' => ['mode' => 'allowList', 'terms' => ['host', 'user-agent', 'accept', 'accept-language', 'content-type']],
            'response' => ['mode' => 'off'],
        ],
        // No request or response bodies (form input, passwords).
        'http_bodies' => [],
        // Query strings can carry signatures (signed verification links).
        'url_query_params' => ['mode' => 'off'],
        'gen_ai' => ['inputs' => false, 'outputs' => false],
        // SQL text without bindings.
        'database_query_data' => false,
        // No queued job payloads.
        'queues' => false,
        // Local variables in stack frames can hold passwords or tokens.
        'stack_frame_variables' => false,
        'frame_context_lines' => 5,
    ],

    'before_send' => [SentryEventScrubber::class, 'beforeSend'],

    'ignore_transactions' => ['/up'],

    'breadcrumbs' => [
        'logs' => true,
        'cache' => false,
        'livewire' => true,
        'sql_queries' => true,
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => true,
        'notifications' => true,
    ],

    'crons' => [
        'monitor_scheduled_tasks' => false,
        'exclude_scheduled_tasks' => [],
    ],

    'tracing' => [
        'default_integrations' => false,
        'queue_job_transactions' => false,
        'queue_jobs' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'sql_origin' => false,
        'views' => false,
        'livewire' => false,
        'http_client_requests' => false,
        'cache' => false,
        'redis_commands' => false,
        'notifications' => false,
        'missing_routes' => false,
        'continue_after_response' => false,
        'gen_ai' => false,
    ],

];
