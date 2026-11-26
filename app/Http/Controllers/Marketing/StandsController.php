<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Shop\ShopCatalog;
use Illuminate\Contracts\View\View;

/**
 * QR stands on the marketing site: active products, bought at registration
 * (/register?stand=) or later in /app.
 */
class StandsController extends Controller
{
    public function __invoke(ShopCatalog $catalog): View
    {
        return view('marketing.stands', [
            'products' => $catalog->products(),
            'settings' => $catalog->settings(),
        ]);
    }
}
