<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MenuType;
use App\Enums\PlatformRole;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantContext;
use Database\Seeders\Concerns\SeedsMenuContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Local development data (never run in production):
 *
 * - platform users: admin@restano.test (super_admin), destek@restano.test (platform_staff)
 * - the theme showcase businesses (ShowcaseSeeder), including "Kahve Konağı"
 *   (kahve-konagi), which gets a team here: owner, manager and branch staff
 * - "Eski Fırın" (eski-firin): a suspended business (unavailable page)
 *
 * Every password is "password". Idempotent: an existing demo tenant or user
 * is left untouched; the demo memberships are re-applied.
 */
class DemoSeeder extends Seeder
{
    use SeedsMenuContent;

    public const string PASSWORD = 'password';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->platformUsers();
            $this->call(ShowcaseSeeder::class);

            $plan = Plan::query()->where('code', ShowcaseSeeder::PLAN_CODE)->sole();
            $this->kahveKonagiTeam();
            $this->suspendedTenant($plan);
        });

        app(TenantContext::class)->clear();
    }

    private function platformUsers(): void
    {
        $this->user('Platform Yöneticisi', 'admin@restano.test', PlatformRole::SuperAdmin);
        $this->user('Platform Destek', 'destek@restano.test', PlatformRole::PlatformStaff);
    }

    /**
     * Members of the showcase business "Kahve Konağı" (email verified: the
     * business panel requires it). The manager and the branch staff work at
     * Moda, so the manager may edit the Moda-only breakfast menu.
     */
    private function kahveKonagiTeam(): void
    {
        $tenant = Tenant::query()->where('slug', 'kahve-konagi')->first();

        if ($tenant === null) {
            return;
        }

        $context = app(TenantContext::class);
        $context->set($tenant);

        $moda = Location::query()->where('slug', 'moda')->first();

        $this->member($tenant, 'Selin Konak', 'sahip@kahvekonagi.test', TenantRole::Owner);

        if ($moda !== null) {
            $this->member($tenant, 'Murat Demir', 'mudur@kahvekonagi.test', TenantRole::Manager, [$moda]);
            $this->member($tenant, 'Ece Yıldız', 'personel@kahvekonagi.test', TenantRole::BranchStaff, [$moda]);
        }

        $context->clear();
    }

    private function suspendedTenant(Plan $plan): void
    {
        if (Tenant::withTrashed()->where('slug', 'eski-firin')->exists()) {
            return;
        }

        $tenant = $this->tenant('eski-firin', ['tr' => ['name' => 'Eski Fırın']], TenantStatus::Suspended);
        $context = app(TenantContext::class);
        $context->set($tenant);

        $tenant->locales()->create(['locale' => 'tr', 'sort_order' => 0]);
        app(SubscriptionService::class)->startTrial($tenant, $plan);
        $location = $this->location('merkez', true, ['city' => 'İzmir'], ['tr' => 'Merkez']);
        $this->menu('menu', MenuType::AllDay, ['tr' => 'Menü'], [$location]);
        $this->member($tenant, 'Hakan Fırıncı', 'sahip@eskifirin.test', TenantRole::Owner);

        $context->clear();
    }

    /**
     * @param  list<Location>  $locations
     */
    private function member(Tenant $tenant, string $name, string $email, TenantRole $role, array $locations = []): void
    {
        $user = $this->user($name, $email);

        $membership = TenantMembership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->first()
            ?? new TenantMembership;
        $membership->forceFill(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), 'role' => $role, 'is_active' => true])->save();

        if ($locations !== []) {
            $membership->locations()->syncWithoutDetaching(array_map(fn (Location $location) => $location->getKey(), $locations));
        }
    }

    private function user(string $name, string $email, ?PlatformRole $role = null): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            return $user;
        }

        $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => Hash::make(self::PASSWORD)]);
        $user->forceFill(['email_verified_at' => now(), 'platform_role' => $role])->save();

        return $user;
    }
}
