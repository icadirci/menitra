<?php

declare(strict_types=1);

namespace App\Services\Registration;

/**
 * Plain data of the welcome mail. Built when the registration is committed,
 * so the queued mail carries no model and needs no tenant context.
 */
final readonly class WelcomeMessage
{
    public function __construct(
        public string $name,
        public string $businessName,
        public string $panelUrl,
        public ?string $trialEndsOn,
        public string $locale,
    ) {}
}
