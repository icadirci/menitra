<?php

declare(strict_types=1);

namespace App\Support\Billing;

final readonly class WebhookNotification
{
    public function __construct(
        public string $token,
        public string $reference,
    ) {}
}
