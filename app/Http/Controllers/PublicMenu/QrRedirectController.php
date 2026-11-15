<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicMenu;

use App\Events\QrCodeScanned;
use App\Http\Controllers\Controller;
use App\Services\Menu\QrCodeResolver;
use Illuminate\Http\RedirectResponse;

use function Illuminate\Support\defer;

/**
 * Stable QR URL → 302 to the menu URL computed now (never 301, so browsers
 * don't cache the target).
 */
class QrRedirectController extends Controller
{
    public function __invoke(string $code, QrCodeResolver $resolver): RedirectResponse
    {
        $target = $resolver->resolve($code);

        defer(fn () => QrCodeScanned::dispatch($target['tenant_id'], $target['qr_code_id'], time()));

        return redirect()->away($target['url'], 302)->header('Cache-Control', 'no-store');
    }
}
