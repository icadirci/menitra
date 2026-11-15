<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Enums\QrBackground;
use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\PlanLimitExceededException;
use App\Filament\App\Resources\QrCodes\Pages\CreateQrCode;
use App\Filament\App\Resources\QrCodes\Pages\EditQrCode;
use App\Filament\App\Resources\QrCodes\Pages\ListQrCodes;
use App\Filament\App\Resources\QrCodes\QrCodeResource;
use App\Models\Location;
use App\Models\Menu;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\QrCodes\QrCodeImage;
use App\Services\QrCodes\QrCodeService;
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

class QrCodeManagementTest extends TestCase
{
    use BuildsMenus, RefreshDatabase;

    private Tenant $tenant;

    private Location $moda;

    private Location $cihangir;

    /** Attached to Moda only. */
    private Menu $breakfast;

    /** Attached to Cihangir only. */
    private Menu $dinner;

    private QrCode $modaCode;

    private QrCode $cihangirCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->subscribedTenant('konak', ['max_qr_codes' => 3]);
        $this->moda = $this->location($this->tenant, 'moda', true);
        $this->cihangir = $this->location($this->tenant, 'cihangir');
        $this->breakfast = $this->menu($this->tenant, 'kahvalti', [$this->moda], name: 'Kahvaltı');
        $this->dinner = $this->menu($this->tenant, 'aksam', [$this->cihangir], name: 'Akşam');
        $this->modaCode = $this->qr($this->tenant, $this->moda, 'Moda vitrin');
        $this->cihangirCode = $this->qr($this->tenant, $this->cihangir, 'Cihangir bahçe');
    }

    private function qr(Tenant $tenant, Location $location, string $label, ?Menu $menu = null): QrCode
    {
        return $this->inTenant($tenant, fn (): QrCode => QrCode::query()->create([
            'location_id' => $location->getKey(),
            'menu_id' => $menu?->getKey(),
            'label' => $label,
        ]));
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

    /**
     * @param  array<string, mixed>  $params
     */
    private function livewire(User $user, string $component, array $params = []): Testable
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->tenant);
        app(TenantContext::class)->set($this->tenant);

        return Livewire::test($component, $params);
    }

    public function test_owner_reaches_every_code(): void
    {
        $owner = $this->owner();

        foreach (['qr-codes', 'qr-codes/create', "qr-codes/{$this->modaCode->id}/edit", "qr-codes/{$this->cihangirCode->id}/edit"] as $path) {
            $this->actingAs($owner)->get("/app/konak/{$path}")->assertOk();
        }

        $this->livewire($owner, ListQrCodes::class)->assertCanSeeTableRecords([$this->modaCode, $this->cihangirCode]);
    }

    public function test_manager_manages_only_codes_of_assigned_branches(): void
    {
        $manager = $this->member('manager', $this->moda);

        $this->livewire($manager, ListQrCodes::class)
            ->assertCanSeeTableRecords([$this->modaCode])
            ->assertCanNotSeeTableRecords([$this->cihangirCode])
            ->assertTableActionVisible('toggleActive', $this->modaCode)
            ->assertTableActionHidden('delete', $this->modaCode);

        $this->actingAs($manager)->get('/app/konak/qr-codes/create')->assertOk();
        $this->actingAs($manager)->get("/app/konak/qr-codes/{$this->modaCode->id}/edit")->assertOk();
        $this->actingAs($manager)->get("/app/konak/qr-codes/{$this->cihangirCode->id}/edit")->assertNotFound();
    }

    public function test_branch_staff_only_views_and_downloads(): void
    {
        $staff = $this->member('branchStaff', $this->moda);

        $this->actingAs($staff)->get('/app/konak/qr-codes')->assertOk();
        $this->actingAs($staff)->get('/app/konak/qr-codes/create')->assertForbidden();
        $this->actingAs($staff)->get("/app/konak/qr-codes/{$this->modaCode->id}/edit")->assertForbidden();

        $this->livewire($staff, ListQrCodes::class)
            ->assertCanSeeTableRecords([$this->modaCode])
            ->assertCanNotSeeTableRecords([$this->cihangirCode])
            ->assertTableActionHidden('toggleActive', $this->modaCode)
            ->assertTableActionHidden('delete', $this->modaCode)
            ->callTableAction('downloadPng', $this->modaCode)
            ->assertFileDownloaded('qr-moda-vitrin-'.strtolower($this->modaCode->code).'.png', contentType: 'image/png');
    }

    public function test_downloads_encode_the_stable_url(): void
    {
        $images = app(QrCodeImage::class);
        $url = $images->url($this->modaCode);

        $this->assertSame('http://localhost/q/'.$this->modaCode->code, $url);
        $this->assertSame($url, (string) (new QRCodeReader)->readFromBlob($images->png($this->modaCode)));

        $svg = $images->svg($this->modaCode);
        $this->assertStringStartsWith('<?xml', $svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('<script', $svg);

        $this->livewire($this->owner(), ListQrCodes::class)
            ->callTableAction('downloadSvg', $this->modaCode)
            ->assertFileDownloaded('qr-moda-vitrin-'.strtolower($this->modaCode->code).'.svg', $svg, 'image/svg+xml');
    }

    public function test_every_background_carries_a_readable_code_of_the_stable_url(): void
    {
        $images = app(QrCodeImage::class);
        $url = $images->url($this->modaCode);

        foreach (QrBackground::cases() as $background) {
            [$x, $y, $width, $height] = $background->frame();
            [$imageWidth, $imageHeight] = getimagesize($background->path()) ?: [0, 0];
            $this->assertLessThanOrEqual($imageWidth, $x + $width, $background->value);
            $this->assertLessThanOrEqual($imageHeight, $y + $height, $background->value);

            $png = $images->pngOnBackground($this->modaCode, $background);

            $this->assertSame('image/png', getimagesizefromstring($png)['mime'] ?? null, $background->value);
            $this->assertSame($url, (string) (new QRCodeReader)->readFromBlob($png), $background->value);
        }

        $this->assertStringStartsWith('data:image/jpeg;base64,', $images->backgroundPreviewDataUri($this->modaCode, QrBackground::WoodLandscape));
    }

    public function test_branch_staff_download_codes_on_a_background(): void
    {
        $staff = $this->member('branchStaff', $this->moda);
        $expected = app(QrCodeImage::class)->pngOnBackground($this->modaCode, QrBackground::WoodLandscape);

        $this->livewire($staff, ListQrCodes::class)
            ->assertTableActionVisible('downloadOnBackground', $this->modaCode)
            ->callTableAction('downloadOnBackground', $this->modaCode, ['background' => 'wood-landscape'])
            ->assertHasNoTableActionErrors()
            ->assertFileDownloaded(
                'qr-moda-vitrin-'.strtolower($this->modaCode->code).'-wood-landscape.png',
                $expected,
                'image/png',
            );

        $this->livewire($this->owner(), EditQrCode::class, ['record' => $this->modaCode->getKey()])
            ->callAction('downloadOnBackground', ['background' => 'cream-portrait'])
            ->assertFileDownloaded('qr-moda-vitrin-'.strtolower($this->modaCode->code).'-cream-portrait.png', contentType: 'image/png');
    }

    public function test_unknown_backgrounds_are_refused(): void
    {
        $this->livewire($this->owner(), ListQrCodes::class)
            ->callTableAction('downloadOnBackground', $this->modaCode, ['background' => '../../../.env'])
            ->assertHasTableActionErrors(['background'])
            ->assertNoFileDownloaded();
    }

    public function test_owner_creates_a_code_deep_linked_to_an_attached_menu(): void
    {
        $this->livewire($this->owner(), CreateQrCode::class)
            ->fillForm(['label' => 'Masa kartı', 'location_id' => $this->moda->getKey(), 'menu_id' => $this->breakfast->getKey(), 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $qr = QrCode::query()->where('label', 'Masa kartı')->sole();
        $this->assertSame($this->moda->getKey(), $qr->location_id);
        $this->assertSame($this->breakfast->getKey(), $qr->menu_id);
        $this->assertMatchesRegularExpression('/^['.QrCode::ALPHABET.']{10}$/', $qr->code);

        $this->get('http://localhost/q/'.$qr->code)->assertRedirect('http://konak.localhost/moda/kahvalti');
    }

    public function test_a_menu_must_be_attached_to_the_chosen_branch(): void
    {
        // The dinner menu exists in the tenant but is attached to Cihangir only.
        $this->livewire($this->owner(), CreateQrCode::class)
            ->fillForm(['label' => 'Yanlış', 'location_id' => $this->moda->getKey()])
            ->set('data.menu_id', $this->dinner->getKey())
            ->call('create')
            ->assertHasFormErrors(['menu_id']);

        // The service enforces it on its own, whatever the form allowed.
        $this->assertThrows(
            fn () => app(QrCodeService::class)->create($this->moda, ['label' => 'Yanlış', 'menu_id' => $this->dinner->getKey()]),
            ValidationException::class,
        );
        $this->assertThrows(
            fn () => app(QrCodeService::class)->update($this->modaCode, $this->moda, ['label' => 'Yanlış', 'menu_id' => $this->dinner->getKey()]),
            ValidationException::class,
        );

        $this->assertFalse(QrCode::query()->where('label', 'Yanlış')->exists());
    }

    public function test_tampered_location_ids_are_refused(): void
    {
        $manager = $this->member('manager', $this->moda);
        $other = $this->subscribedTenant('baska');
        $foreignLocation = $this->location($other, 'x', true);

        foreach ([$this->cihangir->getKey(), $foreignLocation->getKey(), 999999] as $locationId) {
            $this->livewire($manager, CreateQrCode::class)
                ->fillForm(['label' => 'Kurcalanmış'])
                ->set('data.location_id', $locationId)
                ->call('create')
                ->assertHasFormErrors(['location_id']);

            // Second layer behind the option list: the page re-checks the branch (403).
            $this->assertNull(QrCodeResource::usableLocation($locationId));
        }

        $this->assertSame($this->moda->getKey(), QrCodeResource::usableLocation($this->moda->getKey())?->getKey());

        // Moving an own code to a branch that isn't assigned is refused too.
        $this->livewire($manager, EditQrCode::class, ['record' => $this->modaCode->getKey()])
            ->set('data.location_id', $this->cihangir->getKey())
            ->call('save')
            ->assertHasFormErrors(['location_id']);

        $this->assertFalse(QrCode::query()->where('label', 'Kurcalanmış')->exists());
        $this->assertSame($this->moda->getKey(), $this->modaCode->refresh()->location_id);
        $this->assertSame(0, $this->inTenant($other, fn () => QrCode::query()->count()));
    }

    public function test_tampered_menu_ids_of_another_tenant_are_refused(): void
    {
        $other = $this->subscribedTenant('baska');
        $foreignMenu = $this->menu($other, 'menu', [$this->location($other, 'x', true)]);

        $this->livewire($this->owner(), EditQrCode::class, ['record' => $this->modaCode->getKey()])
            ->set('data.menu_id', $foreignMenu->getKey())
            ->call('save')
            ->assertHasFormErrors(['menu_id']);

        $this->assertNull($this->modaCode->refresh()->menu_id);

        // The database rejects the link even without the service.
        $this->expectException(QueryException::class);
        DB::table('qr_codes')->where('id', $this->modaCode->getKey())->update(['menu_id' => $foreignMenu->getKey()]);
    }

    public function test_other_tenants_codes_are_never_reachable(): void
    {
        $other = $this->subscribedTenant('baska');
        $foreignCode = $this->qr($other, $this->location($other, 'x', true), 'Yabancı');
        $owner = $this->owner();

        $this->actingAs($owner)->get("/app/konak/qr-codes/{$foreignCode->id}/edit")->assertNotFound();
        $this->livewire($owner, ListQrCodes::class)->assertCanNotSeeTableRecords([$foreignCode]);

        $this->assertThrows(fn () => app(QrCodeService::class)->setActive($foreignCode, false), CrossTenantWriteException::class);
        $this->assertTrue($foreignCode->refresh()->is_active);
    }

    public function test_editing_never_changes_the_code(): void
    {
        $code = $this->modaCode->code;

        $this->livewire($this->owner(), EditQrCode::class, ['record' => $this->modaCode->getKey()])
            ->fillForm(['label' => 'Yeni etiket', 'menu_id' => $this->breakfast->getKey()])
            ->set('data.code', 'AAAAAAAAAA')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->modaCode->refresh();
        $this->assertSame($code, $this->modaCode->code);
        $this->assertSame('Yeni etiket', $this->modaCode->label);
        $this->assertSame($this->breakfast->getKey(), $this->modaCode->menu_id);
    }

    public function test_deactivation_takes_effect_immediately_and_delete_is_owner_only(): void
    {
        $url = 'http://localhost/q/'.$this->modaCode->code;
        $this->get($url)->assertRedirect('http://konak.localhost/moda');

        $manager = $this->member('manager', $this->moda);
        $this->livewire($manager, ListQrCodes::class)->callTableAction('toggleActive', $this->modaCode);

        $this->assertFalse($this->modaCode->refresh()->is_active);
        $this->get($url)->assertStatus(503);

        $this->assertFalse($manager->can('delete', $this->modaCode));
        $this->livewire($this->owner(), ListQrCodes::class)->callTableAction('delete', $this->modaCode);

        $this->assertNull(QrCode::query()->find($this->modaCode->getKey()));
        $this->get($url)->assertNotFound();
    }

    public function test_the_plan_limit_is_enforced_by_the_service(): void
    {
        $owner = $this->owner();

        $this->livewire($owner, CreateQrCode::class)
            ->fillForm(['label' => 'Üçüncü', 'location_id' => $this->moda->getKey()])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->livewire($owner, ListQrCodes::class)->assertActionDisabled('create');

        $this->assertThrows(
            fn () => app(QrCodeService::class)->create($this->moda, ['label' => 'Dördüncü']),
            PlanLimitExceededException::class,
        );

        // Editing and deactivating existing codes stays possible at the limit.
        app(QrCodeService::class)->setActive($this->modaCode, false);
        $this->assertFalse($this->modaCode->refresh()->is_active);
        $this->assertSame(3, QrCode::query()->count());
    }
}
