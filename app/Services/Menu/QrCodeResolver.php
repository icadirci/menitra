<?php

declare(strict_types=1);

namespace App\Services\Menu;

use App\Exceptions\MenuUnavailableException;
use App\Models\Location;
use App\Models\LocationMenu;
use App\Models\Menu;
use App\Models\QrCode;
use App\Tenancy\ResolvedTenantFactory;
use Illuminate\Support\Facades\Cache;

/**
 * /q/{code} → the current canonical menu URL, computed at scan time.
 * Documented scope bypass: the code identifies the tenant.
 *
 * Unknown code → 404. Inactive code or offline tenant → unavailable page
 * without revealing the tenant. A deep-linked menu that is no longer
 * attached (or published) falls back to the location page.
 */
final class QrCodeResolver
{
    public function __construct(
        private readonly ResolvedTenantFactory $tenants,
        private readonly MenuUrlGenerator $urls,
    ) {}

    /**
     * @return array{url: string, tenant_id: int, qr_code_id: int}
     *
     * @throws MenuUnavailableException
     */
    public function resolve(string $code): array
    {
        $code = QrCode::normalise($code);
        abort_unless(preg_match('/^['.QrCode::ALPHABET.']{10}$/', $code) === 1, 404);

        $data = Cache::remember(MenuCacheKeys::qr($code), (int) config('tenancy.cache.resolution_ttl'), function () use ($code): ?array {
            $qr = QrCode::withoutTenantScope()->where('code', $code)->first();

            return $qr === null ? null : [
                'id' => (int) $qr->getKey(),
                'tenant_id' => (int) $qr->tenant_id,
                'location_id' => (int) $qr->location_id,
                'menu_id' => $qr->menu_id === null ? null : (int) $qr->menu_id,
                'is_active' => $qr->is_active,
            ];
        });

        abort_if($data === null, 404);

        $tenant = $this->tenants->byId($data['tenant_id']);
        abort_if($tenant === null, 404);

        if (! $data['is_active'] || ! $tenant->isOnline()) {
            throw new MenuUnavailableException;
        }

        $location = Location::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->find($data['location_id']);
        abort_if($location === null, 404);

        $url = $this->urls->location($tenant->slug, $location->slug);

        if ($data['menu_id'] !== null) {
            $attached = LocationMenu::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->where('location_id', $location->getKey())
                ->where('menu_id', $data['menu_id'])
                ->where('is_active', true)
                ->exists();
            $menu = $attached
                ? Menu::withoutTenantScope()->where('tenant_id', $tenant->id)->where('is_published', true)->find($data['menu_id'])
                : null;

            if ($menu !== null) {
                $url = $this->urls->menu($tenant->slug, $location->slug, $menu->slug);
            }
        }

        return ['url' => $url, 'tenant_id' => $tenant->id, 'qr_code_id' => $data['id']];
    }
}
