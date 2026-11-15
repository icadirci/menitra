<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Enums\SubscriptionStatus;
use App\Models\Location;
use App\Models\QrCode;
use App\Models\Subscription;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Dashboard\BusinessDashboard;
use App\Services\Marketing\MarketingContent;
use App\Services\Menu\MenuUrlGenerator;
use App\Services\QrCodes\QrCodeImage;
use App\Support\MarketingUrl;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * Read model for /account (overview.md § Authentication): minimal, read-only,
 * not a dashboard. Answers for the tenant in TenantContext (set by
 * ResolveAccountTenant after the membership was verified) and reads only
 * through the tenant scope. The subscription and the online state come from
 * BusinessDashboard, so they follow the same rule as the public menu.
 *
 * Owners (TenantPolicy::manageSettings): subscription, tenant public link,
 * default branch with its default QR code. Managers and branch staff: only
 * whether the menu is online and their assigned branches.
 */
final class AccountOverview
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly BusinessDashboard $dashboard,
        private readonly MenuUrlGenerator $urls,
        private readonly QrCodeImage $qrImages,
        private readonly MarketingContent $marketing,
    ) {}

    public function for(User $user): AccountSummary
    {
        $tenant = $this->context->tenant();
        $membership = $user->membershipFor($tenant) ?? throw new LogicException('No membership for the account tenant.');
        $isOwner = $user->can('manageSettings', $tenant);
        $online = $this->dashboard->isMenuOnline();

        return new AccountSummary(
            businessName: $tenant->translate()?->name ?? $tenant->slug,
            isOwner: $isOwner,
            menuOnline: $online,
            panelUrl: Filament::getPanel('app')->getUrl($tenant),
            publicMenuUrl: $isOwner ? $this->urls->tenant($tenant->slug) : null,
            subscription: $isOwner ? $this->subscription($this->dashboard->latestSubscription()) : null,
            branches: $this->branches($user, $membership, $isOwner),
            contactUrl: $isOwner && ! $online ? MarketingUrl::to('contact') : null,
            support: $isOwner && ! $online ? $this->marketing->contactDetails() : null,
        );
    }

    private function subscription(?Subscription $subscription): AccountSubscription
    {
        if ($subscription === null) {
            return new AccountSubscription(null, null, null, null, null, null, null, false);
        }

        [$field, $date] = match ($subscription->status) {
            SubscriptionStatus::Trialing => ['trial_ends_at', $subscription->trial_ends_at],
            SubscriptionStatus::Active, SubscriptionStatus::PastDue => ['current_period_ends_at', $subscription->current_period_ends_at],
            SubscriptionStatus::Grace => ['grace_ends_at', $subscription->grace_ends_at],
            SubscriptionStatus::Expired => [null, null],
        };

        $live = $subscription->isLive();

        return new AccountSubscription(
            status: $subscription->status,
            planName: $subscription->plan?->translate()?->name ?? $subscription->plan?->code,
            keyDateField: $live && $date !== null ? $field : null,
            keyDate: $live ? $this->date($date) : null,
            keyDateDaysLeft: $live && $date !== null ? $this->daysLeft($date) : null,
            accessUntil: $this->date($subscription->accessUntil()),
            endedAt: $this->date($subscription->ended_at),
            cancelAtPeriodEnd: $live && (bool) $subscription->cancel_at_period_end,
        );
    }

    /**
     * Owners: the default branch only (the panel lists every branch).
     * Other roles: their assigned branches, default first.
     *
     * @return list<AccountBranch>
     */
    private function branches(User $user, TenantMembership $membership, bool $isOwner): array
    {
        $locations = $membership->accessibleLocations()
            ->when($isOwner, fn ($query) => $query->where('is_default', true)->limit(1))
            ->with(['translations', 'defaultQrCode'])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $locations->map(fn (Location $location): AccountBranch => new AccountBranch(
            name: $location->translate()?->name ?? $location->slug,
            isDefault: (bool) $location->is_default,
            isActive: (bool) $location->is_active,
            publicUrl: $this->urls->location($this->context->tenant()->slug, $location->slug),
            qrCode: $this->qrCode($user, $location->defaultQrCode),
        ))->values()->all();
    }

    private function qrCode(User $user, ?QrCode $qrCode): ?AccountQrCode
    {
        if ($qrCode === null || Gate::forUser($user)->denies('view', $qrCode)) {
            return null;
        }

        return new AccountQrCode(
            code: (string) $qrCode->code,
            label: $qrCode->label,
            scanUrl: $this->qrImages->url($qrCode),
            previewDataUri: $this->qrImages->svgDataUri($qrCode),
            svgDownloadUrl: route('account.qr', ['code' => $qrCode->code, 'format' => 'svg']),
            pngDownloadUrl: route('account.qr', ['code' => $qrCode->code, 'format' => 'png']),
        );
    }

    private function daysLeft(CarbonImmutable $date): int
    {
        return max(0, (int) ceil(CarbonImmutable::now()->diffInDays($date, false)));
    }

    /**
     * Same display rule as the panel's subscription page.
     */
    private function date(?CarbonImmutable $value): ?string
    {
        return $value?->setTimezone('Europe/Istanbul')->locale(app()->getLocale())->isoFormat('LL');
    }
}
