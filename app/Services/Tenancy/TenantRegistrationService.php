<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enums\MenuTheme;
use App\Enums\MenuType;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Events\TenantRegistered;
use App\Models\Location;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Services\Themes\ThemeService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Public business registration. Creates, in one transaction, either all of:
 * user, tenant (+ name translation), owner membership, enabled locale,
 * default location, default menu attached to it, default QR code, default
 * theme settings (classic), trial subscription — or nothing. Every new tenant therefore starts trialing on the selected (active, public) plan or the default plan.
 *
 * After commit, TenantRegistered is fired (queued welcome mail).
 *
 * The user never receives a platform role here: platform roles are granted
 * only explicitly (platform:assign-role).
 */
final class TenantRegistrationService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SubscriptionService $subscriptions,
        private readonly ThemeService $themes,
    ) {}

    /**
     * The plan a new business starts its trial on: the requested plan when it
     * is active and public (selectable), otherwise the default plan. The code
     * comes from the visitor (/register?plan=), so it is only ever a hint.
     */
    public function planFor(?string $code): Plan
    {
        $selected = filled($code)
            ? Plan::query()->selectable()->where('code', $code)->first()
            : null;

        return $selected
            ?? Plan::query()->active()->where('code', config('subscriptions.default_plan'))->first()
            ?? throw new RuntimeException('The default subscription plan is missing; run the PlanSeeder.');
    }

    public function register(
        string $name,
        string $email,
        string $password,
        string $businessName,
        string $slug,
        string $locale,
        ?string $planCode = null,
    ): User {
        $plan = $this->planFor($planCode);

        return DB::transaction(function () use ($name, $email, $password, $businessName, $slug, $locale, $plan): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);
            // Registration is only reachable through a form that requires the
            // terms checkbox (CreateNewUser), so acceptance is recorded here.
            $user->recordTermsAcceptance();

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
                // Enabled languages: the registration locale, which is also
                // tenants.default_locale (the single source of the default).
                $tenant->locales()->create(['locale' => $locale, 'sort_order' => 0]);

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

                // Default QR code (stable /q/{code}) for the default location.
                $location->qrCodes()->create(['label' => $businessName]);

                // Settings row of the starting theme (tenants.theme = classic).
                $this->themes->createDefaults(MenuTheme::Classic);

                $subscription = $this->subscriptions->startTrial($tenant, $plan);
            } finally {
                $previous !== null ? $this->context->set($previous) : $this->context->clear();
            }

            // Fired after the transaction commits (ShouldDispatchAfterCommit),
            // never after a rollback: welcome mail (SendWelcomeMail).
            TenantRegistered::dispatch($user, $tenant, $businessName, $locale, $subscription->trial_ends_at);

            return $user;
        });
    }
}
