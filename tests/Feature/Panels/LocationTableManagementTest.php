<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\PlanFeature;
use App\Enums\QrBackground;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PlanLimitExceededException;
use App\Filament\App\Resources\Locations\Pages\EditLocation;
use App\Filament\App\Resources\Locations\Pages\ViewLocation;
use App\Filament\App\Resources\Locations\RelationManagers\TablesRelationManager;
use App\Models\Location;
use App\Models\LocationTable;
use App\Models\QrCode;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Locations\LocationTableService;
use App\Services\QrCodes\QrCodeImage;
use App\Services\QrCodes\QrCodeService;
use App\Services\Subscription\FeatureGate;
use App\Tenancy\TenantContext;
use chillerlan\QRCode\QRCode as QRCodeReader;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\BuildsMenus;
use Tests\TestCase;
use ZipArchive;

class LocationTableManagementTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private Tenant $tenant;

    private Location $moda;

    private Location $cihangir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->subscribedTenant('konak', ['max_tables' => 5, 'max_qr_codes' => 1]);
        $this->moda = $this->location($this->tenant, 'moda', true);
        $this->cihangir = $this->location($this->tenant, 'cihangir');
        app(TenantContext::class)->set($this->tenant);
    }

    private function owner(): User
    {
        return TenantMembership::factory()->for($this->tenant)->create()->user;
    }

    private function member(string $role, Location ...$locations): User
    {
        $membership = TenantMembership::factory()->for($this->tenant)->{$role}()->create();
        $membership->locations()->attach(array_map(fn (Location $location) => $location->getKey(), $locations));

        return $membership->user;
    }

    private function tables(User $user, ?Location $location = null, string $page = EditLocation::class): Testable
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->tenant);
        app(TenantContext::class)->set($this->tenant);

        return Livewire::test(TablesRelationManager::class, [
            'ownerRecord' => $location ?? $this->moda,
            'pageClass' => $page,
        ]);
    }

    /**
     * @return array{Tenant, Location}
     */
    private function otherTenant(): array
    {
        app(TenantContext::class)->clear();

        try {
            $other = $this->subscribedTenant('baska');

            return [$other, $this->location($other, 'x', true)];
        } finally {
            app(TenantContext::class)->set($this->tenant);
        }
    }

    private function service(): LocationTableService
    {
        return app(LocationTableService::class);
    }

    public function test_owner_creates_tables_in_bulk_each_with_its_own_code(): void
    {
        $this->tables($this->owner())
            ->callTableAction('bulkCreate', data: ['prefix' => 'Masa', 'start' => 1, 'count' => 3])
            ->assertHasNoTableActionErrors()
            ->assertNotified(__('panels.app.tables.notifications.created', ['count' => 3]));

        $tables = $this->moda->tables()->with('qrCode')->get();

        $this->assertSame(['Masa 1', 'Masa 2', 'Masa 3'], $tables->pluck('name')->all());
        $this->assertSame([1, 2, 3], $tables->pluck('sort_order')->all());

        foreach ($tables as $table) {
            $this->assertNotNull($table->qrCode);
            $this->assertSame($this->moda->getKey(), $table->qrCode->location_id);
            $this->assertSame($table->name, $table->qrCode->label);
            $this->assertTrue($table->qrCode->is_active);
        }

        $this->assertSame(3, $tables->pluck('qrCode.code')->unique()->count());

        // A table code is a normal stable code: it opens the branch menu.
        $this->get('http://localhost/q/'.$tables[0]->qrCode->code)->assertRedirect('http://konak.localhost/moda');
    }

    public function test_names_are_unique_per_branch(): void
    {
        $this->service()->create($this->moda, ['name' => 'Masa 2']);

        $this->tables($this->owner())
            ->callTableAction('bulkCreate', data: ['prefix' => 'Masa', 'start' => 1, 'count' => 3])
            ->assertNotified(__('panels.app.tables.errors.name_taken', ['names' => 'Masa 2']));

        $this->tables($this->owner())
            ->callTableAction('create', data: ['name' => '  Masa   2 '])
            ->assertNotified(__('panels.app.tables.errors.name_taken', ['names' => 'Masa 2']));

        // Nothing of the refused batch was stored; another branch may reuse the name.
        $this->assertSame(1, LocationTable::query()->count());
        $this->assertSame('Masa 2', $this->service()->create($this->cihangir, ['name' => 'Masa 2'])->name);

        $this->expectException(QueryException::class);
        DB::table('location_tables')->insert([
            'tenant_id' => $this->tenant->getKey(),
            'location_id' => $this->moda->getKey(),
            'name' => 'Masa 2',
        ]);
    }

    public function test_bulk_input_is_validated_by_the_service(): void
    {
        foreach ([[1, 0], [1, LocationTableService::MAX_BULK + 1], [-1, 3], ['x', 3], [1, '2.5']] as [$start, $count]) {
            $this->assertThrows(fn () => $this->service()->createMany($this->moda, 'Masa', $start, $count), ValidationException::class);
        }

        $this->assertThrows(fn () => $this->service()->create($this->moda, ['name' => str_repeat('a', 65)]), ValidationException::class);
        $this->assertThrows(fn () => $this->service()->create($this->moda, ['name' => '   ']), ValidationException::class);

        // Without a prefix the number is the name.
        $this->assertSame(['7', '8'], $this->service()->createMany($this->moda, '', 7, 2)->pluck('name')->all());
    }

    public function test_max_tables_is_enforced_and_table_codes_dont_count_as_qr_codes(): void
    {
        $this->service()->createMany($this->moda, 'Masa', 1, 4);

        // One more fits, two don't: the whole batch is refused.
        $this->assertThrows(fn () => $this->service()->createMany($this->cihangir, 'Bahçe', 1, 2), PlanLimitExceededException::class);
        $this->assertSame(4, LocationTable::query()->count());

        $this->tables($this->owner())
            ->callTableAction('bulkCreate', data: ['prefix' => 'Masa', 'start' => 10, 'count' => 2])
            ->assertNotified((new PlanLimitExceededException(PlanFeature::MaxTables, 5))->getMessage());

        $this->service()->create($this->cihangir, ['name' => 'Bahçe 1']);
        $this->assertThrows(fn () => $this->service()->create($this->cihangir, ['name' => 'Bahçe 2']), PlanLimitExceededException::class);

        // Five table codes exist, max_qr_codes = 1: a branch code still fits.
        $gate = app(FeatureGate::class);
        $this->assertSame(0, $gate->usage($this->tenant, PlanFeature::MaxQrCodes));
        $this->assertSame(5, $gate->usage($this->tenant, PlanFeature::MaxTables));
        app(QrCodeService::class)->create($this->moda, ['label' => 'Vitrin']);
        $this->assertThrows(fn () => app(QrCodeService::class)->create($this->moda, ['label' => 'Vitrin 2']), PlanLimitExceededException::class);
    }

    public function test_without_a_live_subscription_no_table_is_created(): void
    {
        Subscription::query()->update(['ended_at' => now()]);
        app(FeatureGate::class)->flush();

        $this->assertThrows(fn () => $this->service()->create($this->moda, ['name' => 'Masa 1']), PlanLimitExceededException::class);
        $this->assertSame(0, LocationTable::query()->count());
    }

    public function test_managers_manage_tables_of_assigned_branches_but_never_delete(): void
    {
        $table = $this->service()->create($this->moda, ['name' => 'Masa 1']);
        $manager = $this->member('manager', $this->moda);

        $this->tables($manager)
            ->assertTableActionVisible('create')
            ->assertTableActionVisible('bulkCreate')
            ->assertTableActionVisible('edit', $table)
            ->assertTableActionHidden('delete', $table)
            ->callTableAction('edit', $table, ['name' => 'Pencere 1', 'sort_order' => 4])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Pencere 1', $table->refresh()->name);
        $this->assertSame(4, $table->sort_order);
        // The code's label followed the rename.
        $this->assertSame('Pencere 1', $table->qrCode->label);

        $this->assertFalse(TablesRelationManager::canViewForRecord($this->cihangir, EditLocation::class));
        $this->assertTrue($manager->can('update', $this->moda));
        $this->assertFalse($manager->can('update', $this->cihangir));
        $this->assertFalse($manager->can('deleteTable', $this->moda));
    }

    public function test_branch_staff_only_view_and_download(): void
    {
        $table = $this->service()->create($this->moda, ['name' => 'Masa 1']);
        $staff = $this->member('branchStaff', $this->moda);

        $this->tables($staff)
            ->assertCanSeeTableRecords([$table])
            ->assertTableActionHidden('create')
            ->assertTableActionHidden('bulkCreate')
            ->assertTableActionHidden('edit', $table)
            ->assertTableActionHidden('delete', $table)
            ->assertTableActionVisible('downloadAll')
            ->assertTableActionVisible('openCode', $table);

        $this->assertFalse(TablesRelationManager::canViewForRecord($this->cihangir, EditLocation::class));
    }

    public function test_the_view_page_is_read_only_even_for_owners(): void
    {
        $table = $this->service()->create($this->moda, ['name' => 'Masa 1']);

        $this->tables($this->owner(), page: ViewLocation::class)
            ->assertCanSeeTableRecords([$table])
            ->assertTableActionHidden('create')
            ->assertTableActionHidden('bulkCreate')
            ->assertTableActionHidden('delete', $table);
    }

    public function test_owner_deletes_a_table_and_its_code_stops_working_at_once(): void
    {
        $table = $this->service()->create($this->moda, ['name' => 'Masa 1']);
        $url = 'http://localhost/q/'.$table->qrCode->code;
        $this->get($url)->assertRedirect('http://konak.localhost/moda'); // cached target

        $this->tables($this->owner())
            ->callTableAction('delete', $table)
            ->assertHasNoTableActionErrors();

        $this->assertSame(0, LocationTable::query()->count());
        $this->assertSame(0, QrCode::query()->whereNotNull('location_table_id')->count());
        $this->get($url)->assertNotFound();
    }

    public function test_a_table_without_a_code_gets_a_new_one(): void
    {
        $table = $this->service()->create($this->moda, ['name' => 'Masa 1']);
        $old = $table->qrCode->code;
        app(QrCodeService::class)->delete($table->qrCode);

        $this->tables($this->owner())
            ->assertTableActionHidden('openCode', $table)
            ->callTableAction('createCode', $table)
            ->assertNotified(__('panels.app.tables.notifications.code_created'));

        $this->assertNotSame($old, $table->refresh()->qrCode->code);
        $this->assertThrows(fn () => $this->service()->createCode($table), ValidationException::class);
    }

    public function test_a_table_code_never_leaves_its_branch(): void
    {
        $table = $this->service()->create($this->moda, ['name' => 'Masa 1']);
        $code = $table->qrCode;

        $this->assertThrows(fn () => app(QrCodeService::class)->update($code, $this->cihangir, ['label' => 'Masa 1']), ValidationException::class);
        $this->assertSame($this->moda->getKey(), $code->refresh()->location_id);

        // Same branch: label and deep link stay editable.
        app(QrCodeService::class)->update($code, $this->moda, ['label' => 'Masa 1 (pencere)']);
        $this->assertSame('Masa 1 (pencere)', $code->refresh()->label);

        // The database refuses a mismatching branch even without the service.
        $this->expectException(QueryException::class);
        DB::table('qr_codes')->where('id', $code->getKey())->update(['location_id' => $this->cihangir->getKey()]);
    }

    public function test_the_database_refuses_another_tenants_table_or_a_second_code(): void
    {
        $table = $this->service()->create($this->moda, ['name' => 'Masa 1']);
        [$other, $foreignLocation] = $this->otherTenant();

        $insert = fn (array $row) => fn () => DB::transaction(fn () => DB::table('qr_codes')->insert($row + ['code' => QrCode::generateCode(), 'label' => 'x']));

        // Another tenant's code pointing at this tenant's table.
        $this->assertThrows($insert([
            'tenant_id' => $other->getKey(),
            'location_id' => $foreignLocation->getKey(),
            'location_table_id' => $table->getKey(),
        ]), QueryException::class);

        // A second code for the same table.
        $this->assertThrows($insert([
            'tenant_id' => $this->tenant->getKey(),
            'location_id' => $this->moda->getKey(),
            'location_table_id' => $table->getKey(),
        ]), QueryException::class);

        // A table of another tenant's branch.
        $this->assertThrows(fn () => DB::transaction(fn () => DB::table('location_tables')->insert([
            'tenant_id' => $this->tenant->getKey(),
            'location_id' => $foreignLocation->getKey(),
            'name' => 'Yabancı',
        ])), QueryException::class);
    }

    public function test_other_tenants_tables_are_never_reachable(): void
    {
        [$other, $foreignLocation] = $this->otherTenant();
        $foreign = $this->inTenant($other, fn () => $this->service()->create($foreignLocation, ['name' => 'Masa 1']));

        $this->assertSame(0, LocationTable::query()->count());
        $this->assertThrows(fn () => $this->service()->update($foreign, ['name' => 'Ele geçirildi']), CrossTenantWriteException::class);
        $this->assertThrows(fn () => $this->service()->delete($foreign), CrossTenantWriteException::class);
        $this->assertThrows(fn () => $this->service()->create($foreignLocation, ['name' => 'Masa 2']), CrossTenantWriteException::class);

        $this->assertSame('Masa 1', $this->inTenant($other, fn () => $foreign->refresh()->name));
    }

    public function test_table_codes_are_never_a_branch_default_code(): void
    {
        $table = $this->service()->create($this->moda, ['name' => 'Masa 1']);
        $this->assertNull($this->moda->defaultQrCode()->first());

        $branchCode = app(QrCodeService::class)->create($this->moda, ['label' => 'Vitrin']);
        $this->assertTrue($table->qrCode->id < $branchCode->id);
        $this->assertSame($branchCode->id, $this->moda->defaultQrCode()->first()?->id);
    }

    public function test_all_table_codes_of_a_branch_download_as_one_zip(): void
    {
        $tables = $this->service()->createMany($this->moda, 'Masa', 1, 2);
        $this->service()->create($this->cihangir, ['name' => 'Bahçe 1']);
        app(QrCodeService::class)->create($this->moda, ['label' => 'Vitrin']);
        $staff = $this->member('branchStaff', $this->moda);

        $component = $this->tables($staff)->callTableAction('downloadAll', data: ['background' => 'wood-landscape']);
        $component->assertFileDownloaded('masalar-qr-moda.zip', contentType: 'application/zip');

        $path = tempnam(sys_get_temp_dir(), 'zip-test-');
        file_put_contents($path, base64_decode($component->effects['download']['content']));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));

        $images = app(QrCodeImage::class);
        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        // Only this branch's table codes, in table order.
        $this->assertSame($tables->map(fn (LocationTable $table): string => $images->filename($table->qrCode, 'png', QrBackground::WoodLandscape))->all(), $names);
        $this->assertSame($images->url($tables[0]->qrCode), (string) (new QRCodeReader)->readFromBlob((string) $zip->getFromIndex(0)));

        $zip->close();
        unlink($path);
    }
}
