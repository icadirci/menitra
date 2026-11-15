<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\QrCodeScanned;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Keeps qr_codes.last_scanned_at current (the dashboard's "print and scan
 * your QR code" step reads it). Runs after the redirect response (the event
 * is deferred), as one conditional UPDATE: written at most once per
 * THROTTLE_SECONDS per code, so busy codes don't write on every scan.
 *
 * The event carries ids from QrCodeResolver (server-side, never client
 * input); the row is matched by id and tenant_id. A plain table update, so
 * no model events fire: the scan time is not menu content and must not bump
 * cache versions.
 */
final class RecordQrCodeScan
{
    public const int THROTTLE_SECONDS = 3600;

    public function handle(QrCodeScanned $event): void
    {
        $scannedAt = CarbonImmutable::createFromTimestamp($event->scannedAt);

        DB::table('qr_codes')
            ->where('id', $event->qrCodeId)
            ->where('tenant_id', $event->tenantId)
            ->where(fn ($query) => $query
                ->whereNull('last_scanned_at')
                ->orWhere('last_scanned_at', '<', $scannedAt->subSeconds(self::THROTTLE_SECONDS)))
            ->update(['last_scanned_at' => $scannedAt]);
    }
}
