<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Services\QrCodes\QrCodeImage;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /account/qr/{code}.{svg|png}: download a QR image from /account.
 *
 * The code is looked up through the tenant scope (TenantContext set by
 * ResolveAccountTenant), so another tenant's code is a 404; downloading is
 * part of viewing (QrCodePolicy::view: the member's own branches).
 */
class AccountQrCodeController extends Controller
{
    public function __invoke(string $code, string $format, QrCodeImage $images): Response
    {
        $qrCode = QrCode::query()->where('code', QrCode::normalise($code))->firstOrFail();

        Gate::authorize('view', $qrCode);

        return response($format === 'svg' ? $images->svg($qrCode) : $images->png($qrCode), 200, [
            'Content-Type' => $format === 'svg' ? 'image/svg+xml' : 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$images->filename($qrCode, $format).'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
