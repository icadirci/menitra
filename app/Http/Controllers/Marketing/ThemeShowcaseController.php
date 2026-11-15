<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\ThemeShowcase;
use Illuminate\Contracts\View\View;

/**
 * /temalar · /en/themes: every menu theme with a link to its live demo menu.
 */
class ThemeShowcaseController extends Controller
{
    public function __invoke(ThemeShowcase $showcase): View
    {
        return view('marketing.themes', [
            'themes' => $showcase->themes(app()->getLocale()),
        ]);
    }
}
