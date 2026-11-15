<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Analytics hook, dispatched after the QR redirect response. Listener in v1:
 * RecordQrCodeScan (throttled qr_codes.last_scanned_at); scan analytics later.
 */
final class QrCodeScanned
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $qrCodeId,
        public readonly int $scannedAt,
    ) {}
}
