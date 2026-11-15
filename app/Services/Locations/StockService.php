<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Exceptions\CrossTenantWriteException;
use App\Models\Location;
use App\Models\LocationItemState;
use App\Models\MenuItem;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Per-branch sold-out state (location_item_states). The caller authorizes
 * with LocationPolicy::manageStock. Times are stored in UTC; expiry is
 * evaluated at read time, never by a scheduler.
 */
final class StockService
{
    /**
     * @param  CarbonImmutable|null  $until  null = until turned back on
     *
     * @throws ValidationException
     */
    public function markSoldOut(Location $location, MenuItem $item, ?CarbonImmutable $until = null): LocationItemState
    {
        $this->assertSameTenant($location, $item);

        if ($until !== null && $until->isPast()) {
            throw ValidationException::withMessages(['sold_out_until' => __('panels.app.stock.errors.past')]);
        }

        $state = LocationItemState::query()->firstOrNew([
            'location_id' => $location->getKey(),
            'menu_item_id' => $item->getKey(),
        ]);
        $state->fill(['is_sold_out' => true, 'sold_out_until' => $until?->utc()])->save();

        return $state;
    }

    public function markAvailable(Location $location, MenuItem $item): void
    {
        $this->assertSameTenant($location, $item);

        LocationItemState::query()
            ->where('location_id', $location->getKey())
            ->where('menu_item_id', $item->getKey())
            ->get()
            ->each
            ->delete();
    }

    /**
     * End of the current day in the branch's timezone, as UTC.
     */
    public function endOfToday(Location $location): CarbonImmutable
    {
        return CarbonImmutable::now($location->timezone)->endOfDay()->utc();
    }

    private function assertSameTenant(Location $location, MenuItem $item): void
    {
        if ((int) $location->tenant_id !== (int) $item->tenant_id) {
            throw new CrossTenantWriteException('Item and location belong to different tenants.');
        }
    }
}
