<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enums\MenuTheme;
use App\Enums\MenuType;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\Location;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Public business registration. Creates, in one transaction, either all of:
 * user, tenant (+ name translation), owner membership, default location,
 * default menu attached to it, trial subscription — or nothing.
 *
 * The user never receives a platform role here: platform roles are granted
 * only explicitly (platform:assign-role).
 */
final class TenantRegistrationService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function register(
        string $name,
        string $email,
        string $password,
        string $businessName,
        string $slug,
        string $locale,
    ): User {
        $plan = Plan::query()->active()->where('code', config('subscriptions.default_plan'))->first()
            ?? throw new RuntimeException('The default subscription plan is missing; run the PlanSeeder.');

        return DB::transaction(function () use ($name, $email, $password, $businessName, $slug, $locale, $plan): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $tenant = new Tenant([
                'slug' => $slug,
                'currency' => config('subscriptions.currency'),
                'default_locale' => $locale,
                'theme' => MenuTheme::Classic,
            ]);
            $tenant->forceFill(['status' => TenantStatus::Active])->save();
            $tenant->translations()->create(['locale' => $locale, 'name' => $businessName]);

            (new TenantMembership)->forceFill([
                'tenant_id' => $tenant->getKey(),
                'user_id' => $user->getKey(),
                'role' => TenantRole::Owner,
                'is_active' => true,
            ])->save();

            // Documented scope bypass: the tenant was just created, so the
            // remaining tenant-owned rows are written inside its context.
            $previous = $this->context->check() ? $this->context->tenant() : null;
            $this->context->set($tenant);

            try {
                $location = new Location(['slug' => config('tenancy.default_location_slug')]);
                $location->forceFill(['is_default' => true])->save();
                $location->translations()->create(['locale' => $locale, 'name' => $businessName]);

                $menu = Menu::create([
                    'slug' => config('tenancy.default_menu_slug'),
                    'type' => MenuType::AllDay,
                    'is_published' => false,
                ]);
                $menu->translations()->create([
                    'locale' => $locale,
                    'name' => __('registration.default_menu_name', [], $locale),
                ]);
                $location->menus()->attach($menu);

                $this->subscriptions->startTrial($tenant, $plan);
            } finally {
                $previous !== null ? $this->context->set($previous) : $this->context->clear();
            }

            return $user;
        });
    }
}
