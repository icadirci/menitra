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
 * /{location}/{menu}: the menu page.
 */
class MenuController extends Controller
{
    public function __invoke(Request $request, PublicMenuService $menus, ThemeRenderer $renderer): Response
    {
        $tenant = ResolveTenantFromRequest::tenant($request);
        $locale = (string) $request->attributes->get(ResolveMenuLocale::ATTRIBUTE);
        $menus->ensureOnline($tenant, $locale);

        return $renderer->menu($request, $tenant, $menus->menu(
            $tenant,
            $locale,
            (string) $request->route('location'),
            (string) $request->route('menu'),
        ));
    }
}
