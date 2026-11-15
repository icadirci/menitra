<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Enums\PlanFeature;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Location;
use App\Services\Subscription\FeatureGate;
use App\Services\Translations\TranslationWriter;
use App\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Branches of the current tenant. Authorization (LocationPolicy) is checked
 * by the caller; this service enforces the invariants:
 *
 * - creating or restoring a location is limited by the plan (FeatureGate);
 * - the tenant always keeps exactly one default location, which can be
 *   neither deactivated nor deleted (make another branch the default first);
 * - translations go through TranslationWriter, weekly hours through
 *   LocationHoursService.
 *
 * Validation error keys are relative to the submitted data.
 */
final class LocationService
{
    /** Columns written from panel input. Never tenant_id, is_default or theme. */
    private const array ATTRIBUTES = [
        'slug',
        'is_active',
        'phone',
        'email',
        'whatsapp',
        'address_line',
        'district',
        'city',
        'postal_code',
        'country_code',
        'latitude',
        'longitude',
        'timezone',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly FeatureGate $gate,
        private readonly TranslationWriter $translations,
        private readonly LocationHoursService $hours,
    ) {}

    /**
     * @param  array<string, mixed>  $data  attributes, cover_path, translations, opening_hours
     *
     * @throws PlanLimitExceededException
     * @throws ValidationException
     */
    public function create(array $data): Location
    {
        return DB::transaction(function () use ($data): Location {
            // Serialises concurrent creations of the same tenant so the limit holds.
            $this->lockTenant();
            $this->gate->ensureCanCreate($this->context->tenant(), PlanFeature::MaxLocations);

            $location = new Location(Arr::only($data, self::ATTRIBUTES));
            $location->forceFill(['cover_path' => $data['cover_path'] ?? null, 'is_default' => false]);
            $location->sort_order = (int) Location::query()->max('sort_order') + 1;
            $location->save();

            $this->writeRelations($location, $data);

            return $location;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(Location $location, array $data): Location
    {
        $this->assertCurrentTenant($location);

        return DB::transaction(function () use ($location, $data): Location {
            $location->fill(Arr::only($data, self::ATTRIBUTES));

            if (array_key_exists('cover_path', $data)) {
                $location->forceFill(['cover_path' => $data['cover_path']]);
            }

            if ($location->is_default && ! $location->is_active) {
                throw ValidationException::withMessages(['is_active' => __('panels.app.locations.errors.default_inactive')]);
            }

            $location->save();
            $this->writeRelations($location, $data);

            return $location;
        });
    }

    /**
     * Soft delete; the default location can't be deleted.
     *
     * @throws ValidationException
     */
    public function delete(Location $location): void
    {
        $this->assertCurrentTenant($location);

        if ($location->is_default) {
            throw ValidationException::withMessages(['location' => __('panels.app.locations.errors.default_delete')]);
        }

        $location->delete();
    }

    /**
     * Restoring counts as creating: it must fit the plan.
     *
     * @throws PlanLimitExceededException
     */
    public function restore(Location $location): void
    {
        $this->assertCurrentTenant($location);

        DB::transaction(function () use ($location): void {
            $this->lockTenant();
            $this->gate->ensureCanCreate($this->context->tenant(), PlanFeature::MaxLocations);

            $location->restore();
        });
    }

    /**
     * @throws ValidationException
     */
    public function makeDefault(Location $location): void
    {
        $this->assertCurrentTenant($location);

        if ($location->trashed() || ! $location->is_active) {
            throw ValidationException::withMessages(['location' => __('panels.app.locations.errors.default_requires_active')]);
        }

        DB::transaction(function () use ($location): void {
            $this->lockTenant();

            // The partial unique index allows one default: clear the old one first.
            Location::withTrashed()
                ->where('is_default', true)
                ->whereKeyNot($location->getKey())
                ->update(['is_default' => false]);

            $location->forceFill(['is_default' => true])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeRelations(Location $location, array $data): void
    {
        if (array_key_exists('translations', $data)) {
            $this->translations->sync($location, (array) $data['translations'], ['name']);
        }

        if (array_key_exists('opening_hours', $data)) {
            $this->hours->syncOpeningHours($location, (array) $data['opening_hours']);
        }
    }

    private function lockTenant(): void
    {
        DB::table('tenants')->where('id', $this->context->id())->lockForUpdate()->first();
    }

    private function assertCurrentTenant(Location $location): void
    {
        if ((int) $location->tenant_id !== $this->context->id()) {
            throw new CrossTenantWriteException('The location belongs to another tenant.');
        }
    }
}
