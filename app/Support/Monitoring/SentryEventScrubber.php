<?php

declare(strict_types=1);

namespace App\Support\Monitoring;

use App\Tenancy\TenantContext;
use Sentry\Event;
use Sentry\EventHint;

/**
 * `before_send` hook of config/sentry.php. Query strings, bodies, cookies and
 * user data are already excluded by `data_collection`; this removes secrets
 * that sit in URL paths (password reset tokens, e-mail verification hashes,
 * team invitation tokens) and tags the event with the current tenant id
 * (an internal number, not personal data) for debugging.
 */
final class SentryEventScrubber
{
    /**
     * Path patterns whose trailing segments are secrets. The prefix group is kept.
     *
     * @var list<string>
     */
    private const array SECRET_PATHS = [
        '#(/reset-password/)[^/?\#]+#',
        '#(/email/verify/)[^?\#]+#',
        '#(/invitations/)[^/?\#]+#',
    ];

    public const string REDACTED = '[redacted]';

    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        $request = $event->getRequest();

        if (isset($request['url']) && is_string($request['url'])) {
            $request['url'] = self::redactUrl($request['url']);
            $event->setRequest($request);
        }

        $context = app(TenantContext::class);

        if ($context->check()) {
            $event->setTag('tenant_id', (string) $context->id());
        }

        return $event;
    }

    public static function redactUrl(string $url): string
    {
        foreach (self::SECRET_PATHS as $pattern) {
            $url = (string) preg_replace($pattern, '$1'.self::REDACTED, $url);
        }

        return $url;
    }
}
