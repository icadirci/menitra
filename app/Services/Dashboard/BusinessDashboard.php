<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\MenuTheme;
use App\Models\Location;
use App\Models\LocationOpeningHour;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\QrCode;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\ThemeSetting;
use App\Support\Menu\MenuAvailability;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the business dashboard (docs/architecture/overview.md, B10).
 * Every value is derived from stored data at request time; nothing about the
 * dashboard is stored or cached (results depend on the clock and, in the
 * widgets, on the member's branches).
 *
 * Reads only through the tenant scope, so it always answers for the tenant
 * in TenantContext. Scoped singleton: memoised per request.
 */
class BusinessDashboard
{
    private ?Subscription $subscription = null;

    private bool $subscriptionLoaded = false;

    /** @var array{steps: list<OnboardingStep>, default_location_id: int|null}|null */
    private ?array $checklist = null;

    public function __construct(private readonly TenantContext $context) {}

    /**
     * The live subscription (ended_at null) with its plan, or null.
     */
    public function liveSubscription(): ?Subscription
    {
        $subscription = $this->latestSubscription();

        return $subscription?->ended_at === null ? $subscription : null;
    }

    /**
     * The most recent subscription with its plan: the live one if there is
     * one (a new row is only created after the previous one ended), otherwise
     * the last ended one (e.g. expired), or null when the tenant never had one.
     */
    public function latestSubscription(): ?Subscription
    {
        if (! $this->subscriptionLoaded) {
            $this->subscription = Subscription::query()
                ->with('plan.translations')
                ->latest('id')
                ->first();
            $this->subscriptionLoaded = true;
        }

        return $this->subscription;
    }

    /**
     * Same rule as the public menu (MenuAvailability): active tenant and
     * now ≤ access_until.
     */
    public function isMenuOnline(): bool
    {
        return MenuAvailability::isOnline($this->tenant()->isActive(), $this->liveSubscription()?->accessUntil());
    }

    /**
     * @return list<OnboardingStep>
     */
    public function steps(): array
    {
        return $this->checklist()['steps'];
    }

    public function isOnboardingComplete(): bool
    {
        foreach ($this->steps() as $step) {
            if (! $step->done) {
                return false;
            }
        }

        return true;
    }

    public function defaultLocationId(): ?int
    {
        return $this->checklist()['default_location_id'];
    }

    /**
     * All steps in one query (scalar subqueries, each through the tenant scope).
     *
     * @return array{steps: list<OnboardingStep>, default_location_id: int|null}
     */
    private function checklist(): array
    {
        if ($this->checklist !== null) {
            return $this->checklist;
        }

        $tenant = $this->tenant();
        $theme = $tenant->theme ?? MenuTheme::Classic;
        $one = DB::raw('1');

        $row = DB::query()
            ->selectSub(Location::query()->where('is_default', true)->select('id')->limit(1), 'default_location_id')
            // At least one item in a menu that isn't deleted.
            ->selectSub(MenuItem::query()->whereHas('category.menu')->select($one)->limit(1), 'has_items')
            // A published menu attached (actively) to an active branch.
            ->selectSub(Menu::query()
                ->where('is_published', true)
                ->whereHas('locationMenus', fn ($query) => $query
                    ->where('is_active', true)
                    ->whereHas('location', fn ($location) => $location->where('is_active', true)))
                ->select($one)->limit(1), 'has_published')
            ->selectSub(LocationOpeningHour::query()
                ->whereHas('location', fn ($location) => $location->where('is_default', true))
                ->select($one)->limit(1), 'has_hours')
            // Registration writes the classic row; a later save means it was reviewed.
            ->selectSub(ThemeSetting::query()
                ->where('theme', $theme->value)
                ->whereColumn('updated_at', '>', 'created_at')
                ->select($one)->limit(1), 'theme_customised')
            ->selectSub(QrCode::query()->whereNotNull('last_scanned_at')->select($one)->limit(1), 'qr_scanned')
            ->first();

        return $this->checklist = [
            'default_location_id' => $row->default_location_id === null ? null : (int) $row->default_location_id,
            'steps' => [
                new OnboardingStep(OnboardingStep::PROFILE, filled($tenant->logo_path)),
                new OnboardingStep(OnboardingStep::ITEMS, $row->has_items !== null),
                new OnboardingStep(OnboardingStep::PUBLISH, $row->has_published !== null),
                new OnboardingStep(OnboardingStep::HOURS, $row->has_hours !== null),
                new OnboardingStep(OnboardingStep::THEME, $theme !== MenuTheme::Classic || $row->theme_customised !== null),
                new OnboardingStep(OnboardingStep::QR, $row->qr_scanned !== null),
            ],
        ];
    }

    private function tenant(): Tenant
    {
        return $this->context->tenant();
    }
}
