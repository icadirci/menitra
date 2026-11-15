<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicMenu;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveMenuLocale;
use App\Http\Middleware\ResolveTenantFromRequest;
use App\Services\Menu\PublicMenuService;
use App\Services\Menu\ThemeRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "/" of a tenant: the location index, or the only location directly (and
 * its only menu directly).
 */
class TenantHomeController extends Controller
{
    public function __invoke(Request $request, PublicMenuService $menus, ThemeRenderer $renderer): Response
    {
        $tenant = ResolveTenantFromRequest::tenant($request);
        $locale = (string) $request->attributes->get(ResolveMenuLocale::ATTRIBUTE);
        $menus->ensureOnline($tenant, $locale);

        $index = $menus->locations($tenant, $locale);
        abort_if($index->locations === [], 404);

        if (count($index->locations) > 1) {
            return $renderer->locationIndex($request, $tenant, $index);
        }

        $snapshot = $menus->location($tenant, $locale, $index->locations[0]->slug);

        if (count($snapshot->menus) === 1) {
            return $renderer->menu($request, $tenant, $menus->menu($tenant, $locale, $snapshot->location->slug, $snapshot->menus[0]->slug));
        }

        return $renderer->location($request, $tenant, $snapshot);
    }
}
