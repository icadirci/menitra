<?php

declare(strict_types=1);

namespace App\Services\Account;

/**
 * A branch the member may act on, with its public menu URL (MenuUrlGenerator)
 * and its default QR code when the member may view it (QrCodePolicy::view).
 */
final readonly class AccountBranch
{
    public function __construct(
        public string $name,
        public bool $isDefault,
        public bool $isActive,
        public string $publicUrl,
        public ?AccountQrCode $qrCode,
    ) {}
}
