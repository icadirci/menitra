<?php

declare(strict_types=1);

namespace App\Services\Menu;

use App\Enums\MenuTheme;
use App\Support\Menu\Data\MenuSnapshot;
use App\Support\Menu\Data\TenantLocations;
use App\Support\Menu\LiveMenuState;
use App\Support\Menu\ThemeSettings;
use App\Tenancy\ResolvedTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the effective theme (location override ?? tenant theme), falls back
 * to `classic` when the plan lacks the theme's feature (settings are kept)
 * or the theme isn't built yet (Phase 7), and renders it with exactly
 * $menu, $live and $theme. Adds ETag and short public caching.
 */
final class ThemeRenderer
{
    public function menu(Request $request, ResolvedTenant $tenant, MenuSnapshot $snapshot): Response
    {
        return $this->render($request, $tenant, $snapshot, fn (MenuTheme $theme): string => $theme->view());
    }

    public function location(Request $request, ResolvedTenant $tenant, MenuSnapshot $snapshot): Response
    {
        return $this->render($request, $tenant, $snapshot, fn (MenuTheme $theme): string => $theme->locationView());
    }

    public function locationIndex(Request $request, ResolvedTenant $tenant, TenantLocations $locations): Response
    {
        $theme = $this->theme($tenant, null, fn (MenuTheme $theme): string => $theme->locationView());

        return $this->respond($request, view('menu.location-index', [
            'index' => $locations,
            'theme' => ThemeSettings::defaults($theme),
        ]), "{$locations->version}.{$locations->locale}.{$theme->value}");
    }

    public function theme(ResolvedTenant $tenant, ?string $locationTheme, \Closure $view): MenuTheme
    {
        $theme = MenuTheme::tryFrom($locationTheme ?? $tenant->theme) ?? MenuTheme::Classic;

        if ($theme->requiredFeature() !== null && ! $tenant->premiumThemes) {
            $theme = MenuTheme::Classic;
        }

        return View::exists($view($theme)) ? $theme : MenuTheme::Classic;
    }

    private function render(Request $request, ResolvedTenant $tenant, MenuSnapshot $snapshot, \Closure $view): Response
    {
        $theme = $this->theme($tenant, $snapshot->location->theme, $view);

        return $this->respond($request, view($view($theme), [
            'menu' => $snapshot,
            'live' => LiveMenuState::for($snapshot->location),
            'theme' => ThemeSettings::defaults($theme),
        ]), "{$snapshot->version}.{$snapshot->locale}.{$theme->value}");
    }

    /**
     * The ETag also carries the current minute: live state (open now,
     * timed sold-out) changes with the clock even when the snapshot doesn't.
     */
    private function respond(Request $request, \Illuminate\Contracts\View\View $view, string $tag): Response
    {
        $etag = '"'.$tag.'.'.gmdate('YmdHi').'"';

        $response = response($view->render())
            ->header('Cache-Control', 'public, max-age=60')
            ->setEtag(trim($etag, '"'));

        $response->isNotModified($request);

        return $response;
    }
}
