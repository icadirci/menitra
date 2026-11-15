<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

/**
 * One onboarding checklist step, derived from stored data (nothing about the
 * checklist itself is stored).
 */
final readonly class OnboardingStep
{
    public const string PROFILE = 'profile';

    public const string ITEMS = 'items';

    public const string PUBLISH = 'publish';

    public const string HOURS = 'hours';

    public const string THEME = 'theme';

    public const string QR = 'qr';

    public function __construct(
        public string $key,
        public bool $done,
    ) {}
}
