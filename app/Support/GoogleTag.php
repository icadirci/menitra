<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

/**
 * Google Ads tag on the marketing site (docs/architecture/tenancy.md § Security headers).
 *
 * The tag is opt-in per page: only the marketing pages, the registration page
 * and the email verification page render <x-google-tag /> (never QR menus,
 * /account, the panels, invitations or password reset, whose URLs carry
 * secrets). Rendering marks the request so SecurityHeaders widens the CSP for
 * that response only. The browser loads the tag only after cookie consent
 * (resources/js/lib/google-tag.js).
 */
final class GoogleTag
{
    /** CSP sources the tag needs, added only to responses that render it. */
    public const array CSP = [
        'script-src' => ['https://www.googletagmanager.com', 'https://www.googleadservices.com', 'https://googleads.g.doubleclick.net', 'https://www.google.com'],
        'img-src' => ['https://www.googletagmanager.com', 'https://www.googleadservices.com', 'https://googleads.g.doubleclick.net', 'https://www.google.com', 'https://www.google.com.tr', 'https://ad.doubleclick.net'],
        'connect-src' => ['https://www.googletagmanager.com', 'https://www.googleadservices.com', 'https://googleads.g.doubleclick.net', 'https://www.google.com', 'https://www.google.com.tr', 'https://pagead2.googlesyndication.com', 'https://ad.doubleclick.net'],
        'frame-src' => ['https://www.googletagmanager.com', 'https://td.doubleclick.net', 'https://bid.g.doubleclick.net'],
    ];

    private const string SIGNUP_KEY = 'google_tag.signup_conversion';

    private const string RENDERED_ATTRIBUTE = 'google_tag.rendered';

    /** The configured tag id (e.g. AW-123), or null when unset or malformed. */
    public function id(): ?string
    {
        $id = config('services.google_ads.id');

        return is_string($id) && preg_match('/^(AW|G|GT)-[A-Z0-9]{4,20}$/', $id) === 1 ? $id : null;
    }

    /** The sign-up conversion target (AW-123/label), or null. */
    public function signupConversion(): ?string
    {
        $target = config('services.google_ads.signup_conversion');

        return $this->id() !== null && is_string($target) && preg_match('#^AW-\d{4,20}/[A-Za-z0-9_-]{4,64}$#', $target) === 1
            ? $target
            : null;
    }

    public function enabled(): bool
    {
        return $this->id() !== null;
    }

    public function markRendered(Request $request): void
    {
        $request->attributes->set(self::RENDERED_ATTRIBUTE, true);
    }

    public function rendered(Request $request): bool
    {
        return $request->attributes->get(self::RENDERED_ATTRIBUTE) === true;
    }

    /** Called after a successful registration; reported once on the next page with the tag. */
    public function queueSignupConversion(Session $session): void
    {
        if ($this->signupConversion() !== null) {
            $session->put(self::SIGNUP_KEY, true);
        }
    }

    public function pullSignupConversion(Session $session): bool
    {
        return $session->pull(self::SIGNUP_KEY, false) === true;
    }
}
