<?php

declare(strict_types=1);

namespace App\Services\Menus;

use App\Enums\PlanFeature;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Location;
use App\Models\LocationMenu;
use App\Models\Menu;
use App\Services\Locations\LocationHoursService;
use App\Services\Subscription\FeatureGate;
use App\Services\Translations\TranslationWriter;
use App\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menus of the current tenant and their attachment to locations. Authorization
 * (MenuPolicy) is checked by the caller; this service enforces the plan limit
 * on create/restore and writes translations, attachments and serving hours.
 */
final class MenuService
{
    private const array ATTRIBUTES = ['slug', 'type', 'is_published'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly FeatureGate $gate,
        private readonly TranslationWriter $translations,
        private readonly LocationHoursService $hours,
    ) {}

    /**
     * @param  array<string, mixed>  $data  slug, type, is_published, translations
     *
     * @throws PlanLimitExceededException
     * @throws ValidationException
     */
    public function create(array $data): Menu
    {
        return DB::transaction(function () use ($data): Menu {
            $this->lockTenant();
            $this->gate->ensureCanCreate($this->context->tenant(), PlanFeature::MaxMenus);

            $menu = new Menu(Arr::only($data, self::ATTRIBUTES));
            $menu->sort_order = (int) Menu::query()->max('sort_order') + 1;
            $menu->save();

            $this->translations->sync($menu, (array) ($data['translations'] ?? []), ['name']);

            return $menu;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(Menu $menu, array $data): Menu
    {
        $this->assertCurrentTenant($menu);

        return DB::transaction(function () use ($menu, $data): Menu {
            $menu->update(Arr::only($data, self::ATTRIBUTES));

            if (array_key_exists('translations', $data)) {
                $this->translations->sync($menu, (array) $data['translations'], ['name']);
            }

            return $menu;
        });
    }

    public function delete(Menu $menu): void
    {
        $this->assertCurrentTenant($menu);
        $menu->delete();
    }

    /**
     * @throws PlanLimitExceededException
     */
    public function restore(Menu $menu): void
    {
        $this->assertCurrentTenant($menu);

        DB::transaction(function () use ($menu): void {
            $this->lockTenant();
            $this->gate->ensureCanCreate($this->context->tenant(), PlanFeature::MaxMenus);
            $menu->restore();
        });
    }

    /**
     * @param  array<string, mixed>  $data  is_active, sort_order, hours
     *
     * @throws ValidationException
     */
    public function attach(Menu $menu, Location $location, array $data = []): LocationMenu
    {
        $this->assertCurrentTenant($menu);

        if ((int) $location->tenant_id !== (int) $menu->tenant_id) {
            throw new CrossTenantWriteException('The location belongs to another tenant.');
        }

        if (LocationMenu::query()->where('menu_id', $menu->getKey())->where('location_id', $location->getKey())->exists()) {
            throw ValidationException::withMessages(['location_id' => __('panels.app.menus.errors.already_attached')]);
        }

        return DB::transaction(function () use ($menu, $location, $data): LocationMenu {
            $attachment = new LocationMenu([
                'location_id' => $location->getKey(),
                'menu_id' => $menu->getKey(),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'sort_order' => (int) ($data['sort_order'] ?? (LocationMenu::query()->where('location_id', $location->getKey())->max('sort_order') + 1)),
            ]);
            $attachment->save();

            if (array_key_exists('hours', $data)) {
                $this->hours->syncMenuHours($attachment, (array) $data['hours']);
            }

            return $attachment;
        });
    }

    /**
     * @param  array<string, mixed>  $data  is_active, sort_order, hours
     *
     * @throws ValidationException
     */
    public function updateAttachment(LocationMenu $attachment, array $data): LocationMenu
    {
        return DB::transaction(function () use ($attachment, $data): LocationMenu {
            $attachment->update(Arr::only($data, ['is_active', 'sort_order']));

            if (array_key_exists('hours', $data)) {
                $this->hours->syncMenuHours($attachment, (array) $data['hours']);
            }

            return $attachment;
        });
    }

    public function detach(LocationMenu $attachment): void
    {
        $attachment->delete();
    }

    private function lockTenant(): void
    {
        DB::table('tenants')->where('id', $this->context->id())->lockForUpdate()->first();
    }

    private function assertCurrentTenant(Menu $menu): void
    {
        if ((int) $menu->tenant_id !== $this->context->id()) {
            throw new CrossTenantWriteException('The menu belongs to another tenant.');
        }
    }
}
