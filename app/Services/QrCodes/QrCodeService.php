<?php

declare(strict_types=1);

namespace App\Services\QrCodes;

use App\Enums\PlanFeature;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Location;
use App\Models\LocationMenu;
use App\Models\QrCode;
use App\Services\Subscription\FeatureGate;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * QR codes of the current tenant. The caller authorizes (QrCodePolicy, incl.
 * manageAt() for the target branch); this service enforces max_qr_codes on
 * create and the integrity rules: the branch belongs to the tenant and isn't
 * deleted, and a deep-linked menu is attached to that branch.
 *
 * The code itself is never changed: printed codes must keep working. Table
 * codes are created with their table (LocationTableService) and never move
 * to another branch.
 */
final class QrCodeService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly FeatureGate $gate,
    ) {}

    /**
     * @param  array<string, mixed>  $data  menu_id, label, is_active
     *
     * @throws PlanLimitExceededException
     * @throws ValidationException
     */
    public function create(Location $location, array $data): QrCode
    {
        $this->assertUsableLocation($location);
        $menuId = $this->menuId($location, $data['menu_id'] ?? null);

        return DB::transaction(function () use ($location, $data, $menuId): QrCode {
            DB::table('tenants')->where('id', $this->context->id())->lockForUpdate()->first();
            $this->gate->ensureCanCreate($this->context->tenant(), PlanFeature::MaxQrCodes);

            return QrCode::query()->create([
                'location_id' => $location->getKey(),
                'menu_id' => $menuId,
                'label' => $this->label($data),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data  menu_id, label, is_active
     *
     * @throws ValidationException
     */
    public function update(QrCode $qrCode, Location $location, array $data): QrCode
    {
        $this->assertCurrentTenant($qrCode);
        $this->assertUsableLocation($location);

        if ($qrCode->isTableCode() && (int) $location->getKey() !== (int) $qrCode->location_id) {
            throw ValidationException::withMessages(['location_id' => __('panels.app.qr_codes.errors.table_location')]);
        }

        $qrCode->update([
            'location_id' => $location->getKey(),
            'menu_id' => $this->menuId($location, $data['menu_id'] ?? null),
            'label' => $this->label($data),
            'is_active' => (bool) ($data['is_active'] ?? $qrCode->is_active),
        ]);

        return $qrCode;
    }

    public function setActive(QrCode $qrCode, bool $active): void
    {
        $this->assertCurrentTenant($qrCode);
        $qrCode->update(['is_active' => $active]);
    }

    public function delete(QrCode $qrCode): void
    {
        $this->assertCurrentTenant($qrCode);
        $qrCode->delete();
    }

    /**
     * @throws ValidationException
     */
    private function menuId(Location $location, mixed $menuId): ?int
    {
        if ($menuId === null || $menuId === '') {
            return null;
        }

        // Tenant-scoped: another tenant's menu is never "attached" here.
        $attached = is_numeric($menuId) && LocationMenu::query()
            ->where('location_id', $location->getKey())
            ->where('menu_id', (int) $menuId)
            ->whereHas('menu')
            ->exists();

        if (! $attached) {
            throw ValidationException::withMessages(['menu_id' => __('panels.app.qr_codes.errors.menu_not_attached')]);
        }

        return (int) $menuId;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function label(array $data): string
    {
        $label = trim((string) ($data['label'] ?? ''));

        if ($label === '' || mb_strlen($label) > 120) {
            throw ValidationException::withMessages(['label' => __('panels.app.qr_codes.errors.label')]);
        }

        return $label;
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

    private function assertCurrentTenant(QrCode $qrCode): void
    {
        if ((int) $qrCode->tenant_id !== $this->context->id()) {
            throw new CrossTenantWriteException('The QR code belongs to another tenant.');
        }
    }
}
