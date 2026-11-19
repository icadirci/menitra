<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Enums\PlanFeature;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Location;
use App\Models\LocationTable;
use App\Models\QrCode;
use App\Services\Subscription\FeatureGate;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tables of the current tenant's branches. The caller authorizes
 * (LocationPolicy::update for creating/editing, ::deleteTable for deleting);
 * this service enforces max_tables, unique names per branch and the
 * integrity rules.
 *
 * Every table is created with its own QR code (same branch, label = table
 * name). Table codes count against max_tables only, never max_qr_codes.
 */
final class LocationTableService
{
    public const int MAX_NAME_LENGTH = 64;

    public const int MAX_BULK = 100;

    public function __construct(
        private readonly TenantContext $context,
        private readonly FeatureGate $gate,
    ) {}

    /**
     * @param  array<string, mixed>  $data  name, sort_order
     *
     * @throws PlanLimitExceededException
     * @throws ValidationException
     */
    public function create(Location $location, array $data): LocationTable
    {
        $this->assertUsableLocation($location);
        $name = $this->name($data['name'] ?? null);

        return DB::transaction(function () use ($location, $name, $data): LocationTable {
            $this->lockTenantAndCheckLimit(1);
            $this->assertNamesFree($location, [$name]);

            return $this->createWithCode($location, $name, (int) ($data['sort_order'] ?? $this->nextSortOrder($location)));
        });
    }

    /**
     * "Masa 1" … "Masa 20": $count tables named "{prefix} {n}" from $start.
     *
     * @return Collection<int, LocationTable>
     *
     * @throws PlanLimitExceededException
     * @throws ValidationException
     */
    public function createMany(Location $location, mixed $prefix, mixed $start, mixed $count): Collection
    {
        $this->assertUsableLocation($location);

        $prefix = trim((string) $prefix);
        $start = filter_var($start, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9999]]);
        $count = filter_var($count, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_BULK]]);

        if ($start === false) {
            throw ValidationException::withMessages(['start' => __('panels.app.tables.errors.start')]);
        }

        if ($count === false) {
            throw ValidationException::withMessages(['count' => __('panels.app.tables.errors.count', ['max' => self::MAX_BULK])]);
        }

        $names = array_map(fn (int $n): string => $this->name($prefix === '' ? (string) $n : "{$prefix} {$n}", 'prefix'), range($start, $start + $count - 1));

        return DB::transaction(function () use ($location, $names): Collection {
            $this->lockTenantAndCheckLimit(count($names));
            $this->assertNamesFree($location, $names);

            $sortOrder = $this->nextSortOrder($location);

            return collect($names)->map(fn (string $name, int $i): LocationTable => $this->createWithCode($location, $name, $sortOrder + $i));
        });
    }

    /**
     * Renames a table; its code's label follows when it still shows the old
     * name. The branch never changes.
     *
     * @param  array<string, mixed>  $data  name, sort_order
     *
     * @throws ValidationException
     */
    public function update(LocationTable $table, array $data): LocationTable
    {
        $this->assertCurrentTenant($table);
        $name = $this->name($data['name'] ?? null);

        return DB::transaction(function () use ($table, $name, $data): LocationTable {
            $previous = $table->name;

            if ($name !== $previous) {
                $this->assertNamesFree($table->location()->withTrashed()->firstOrFail(), [$name], $table);
            }

            $table->update([
                'name' => $name,
                'sort_order' => (int) ($data['sort_order'] ?? $table->sort_order),
            ]);

            $code = $table->qrCode;

            if ($code !== null && $code->label === $previous) {
                $code->update(['label' => $name]);
            }

            return $table;
        });
    }

    /**
     * Creates the code of a table whose code was deleted.
     *
     * @throws ValidationException
     */
    public function createCode(LocationTable $table): QrCode
    {
        $this->assertCurrentTenant($table);

        return DB::transaction(function () use ($table): QrCode {
            LocationTable::query()->whereKey($table->getKey())->lockForUpdate()->firstOrFail();

            if ($table->qrCode()->exists()) {
                throw ValidationException::withMessages(['table' => __('panels.app.tables.errors.code_exists')]);
            }

            return $this->newCode($table);
        });
    }

    /**
     * Deletes the table and its code: the printed code stops working.
     */
    public function delete(LocationTable $table): void
    {
        $this->assertCurrentTenant($table);

        DB::transaction(function () use ($table): void {
            // Through the model, so the cached /q/{code} target is forgotten
            // (MenuCacheObserver); the FK cascade is only the safety net.
            $table->qrCode?->delete();
            $table->delete();
        });
    }

    private function createWithCode(Location $location, string $name, int $sortOrder): LocationTable
    {
        $table = LocationTable::query()->create([
            'location_id' => $location->getKey(),
            'name' => $name,
            'sort_order' => $sortOrder,
        ]);

        $this->newCode($table);

        return $table;
    }

    private function newCode(LocationTable $table): QrCode
    {
        return QrCode::query()->create([
            'location_id' => $table->location_id,
            'location_table_id' => $table->getKey(),
            'label' => $table->name,
            'is_active' => true,
        ]);
    }

    /**
     * @throws PlanLimitExceededException
     */
    private function lockTenantAndCheckLimit(int $adding): void
    {
        DB::table('tenants')->where('id', $this->context->id())->lockForUpdate()->first();

        $tenant = $this->context->tenant();
        $limit = $this->gate->limit($tenant, PlanFeature::MaxTables);

        if ($limit !== null && $this->gate->usage($tenant, PlanFeature::MaxTables) + $adding > $limit) {
            throw new PlanLimitExceededException(PlanFeature::MaxTables, $limit);
        }
    }

    /**
     * @param  list<string>  $names
     *
     * @throws ValidationException
     */
    private function assertNamesFree(Location $location, array $names, ?LocationTable $except = null): void
    {
        $taken = LocationTable::query()
            ->where('location_id', $location->getKey())
            ->whereIn('name', $names)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->orderBy('sort_order')
            ->pluck('name');

        if ($taken->isNotEmpty()) {
            throw ValidationException::withMessages([
                count($names) === 1 ? 'name' : 'prefix' => __('panels.app.tables.errors.name_taken', ['names' => $taken->take(5)->implode(', ')]),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function name(mixed $value, string $field = 'name'): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw ValidationException::withMessages([$field => __('panels.app.tables.errors.name', ['max' => self::MAX_NAME_LENGTH])]);
        }

        return $name;
    }

    private function nextSortOrder(Location $location): int
    {
        return (int) LocationTable::query()->where('location_id', $location->getKey())->max('sort_order') + 1;
    }

    /**
     * @throws ValidationException
     */
    private function assertUsableLocation(Location $location): void
    {
        if ((int) $location->tenant_id !== $this->context->id()) {
            throw new CrossTenantWriteException('The location belongs to another tenant.');
        }

        if ($location->trashed()) {
            throw ValidationException::withMessages(['location_id' => __('panels.app.qr_codes.errors.location_deleted')]);
        }
    }

    private function assertCurrentTenant(LocationTable $table): void
    {
        if ((int) $table->tenant_id !== $this->context->id()) {
            throw new CrossTenantWriteException('The table belongs to another tenant.');
        }
    }
}
