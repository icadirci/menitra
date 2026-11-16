<?php

declare(strict_types=1);

namespace App\Services\Team;

use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Branches submitted for an invitation or a member: at least one, every id
 * one of the current tenant's non-deleted locations (tenant scope). Any
 * unknown or foreign id rejects the whole selection.
 */
final class BranchSelection
{
    /**
     * @param  array<mixed>  $locationIds
     * @return Collection<int, Location>
     *
     * @throws ValidationException
     */
    public function resolve(array $locationIds): Collection
    {
        $valid = collect($locationIds)
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn (int|string $id): int => (int) $id);
        $ids = $valid->unique()->values();

        $locations = $ids->isEmpty() ? new Collection : Location::query()->whereIn('id', $ids)->get();

        if ($valid->count() !== count($locationIds) || $locations->isEmpty() || $locations->count() !== $ids->count()) {
            throw ValidationException::withMessages(['location_ids' => __('team.errors.locations')]);
        }

        return $locations;
    }
}
