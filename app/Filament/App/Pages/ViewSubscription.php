<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\PlanFeature;
use App\Filament\App\Navigation\AppNavigationGroup;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Subscription\FeatureGate;
use App\Tenancy\TenantContext;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Read-only subscription overview: plan, status, key dates and plan limits
 * with current usage. Nothing here changes a subscription (state changes go
 * only through SubscriptionService; payments are a later phase).
 * Owner-only (TenantPolicy::manageSettings).
 */
class ViewSubscription extends Page
{
    protected static string|UnitEnum|null $navigationGroup = AppNavigationGroup::Business;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'subscription';

    private ?Subscription $subscription = null;

    private bool $loaded = false;

    public static function canAccess(): bool
    {
        $context = app(TenantContext::class);
        $user = auth()->user();

        return $context->check()
            && $user instanceof User
            && $user->can('manageSettings', $context->tenant());
    }

    public static function getNavigationLabel(): string
    {
        return __('panels.app.subscription.navigation');
    }

    public function getTitle(): string
    {
        return __('panels.app.subscription.title');
    }

    public function content(Schema $schema): Schema
    {
        $subscription = $this->subscription();

        if ($subscription === null) {
            return $schema->components([
                Section::make(__('panels.app.subscription.none.title'))
                    ->schema([Text::make(__('panels.app.subscription.none.body'))]),
            ]);
        }

        return $schema->components(array_values(array_filter([
            $subscription->isLive() ? null : Section::make(__('panels.app.subscription.expired.title'))
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->schema([Text::make(__('panels.app.subscription.expired.body'))]),
            Section::make(__('panels.app.subscription.sections.plan'))
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('plan')
                        ->label(__('panels.app.subscription.fields.plan'))
                        ->state($subscription->plan?->translate()?->name ?? $subscription->plan?->code),
                    TextEntry::make('status')
                        ->label(__('panels.app.subscription.fields.status'))
                        ->state($subscription->status->label())
                        ->badge()
                        ->color($subscription->status->color()),
                    TextEntry::make('access_until')
                        ->label(__('panels.app.subscription.fields.access_until'))
                        ->state($this->date($subscription->accessUntil()))
                        ->helperText(__('panels.app.subscription.fields.access_until_help')),
                    ...$this->dateEntries($subscription),
                ]),
            Section::make(__('panels.app.subscription.sections.limits'))
                ->description(__('panels.app.subscription.sections.limits_help'))
                ->columns(['default' => 1, 'md' => 3])
                ->schema($this->limitEntries()),
            Section::make()
                ->schema([Text::make(__('panels.app.subscription.contact'))]),
        ])));
    }

    /**
     * @return list<TextEntry>
     */
    private function dateEntries(Subscription $subscription): array
    {
        $dates = [
            'trial_ends_at' => $subscription->trial_ends_at,
            'current_period_ends_at' => $subscription->current_period_ends_at,
            'grace_ends_at' => $subscription->grace_ends_at,
            'ended_at' => $subscription->ended_at,
        ];

        $entries = [];

        foreach ($dates as $field => $value) {
            if ($value !== null) {
                $entries[] = TextEntry::make($field)
                    ->label(__("panels.app.subscription.fields.{$field}"))
                    ->state($this->date($value));
            }
        }

        if ($subscription->cancel_at_period_end) {
            $entries[] = TextEntry::make('cancel_at_period_end')
                ->label(__('panels.app.subscription.fields.cancel_at_period_end'))
                ->state(__('panels.app.subscription.fields.cancel_at_period_end_yes'))
                ->color('warning');
        }

        return $entries;
    }

    /**
     * @return list<TextEntry|IconEntry>
     */
    private function limitEntries(): array
    {
        $gate = app(FeatureGate::class);
        $tenant = $this->tenant();
        $entries = [];

        foreach (PlanFeature::cases() as $feature) {
            $limit = $gate->limit($tenant, $feature);

            if ($feature->isBoolean()) {
                $entries[] = IconEntry::make($feature->value)
                    ->label($feature->label())
                    ->state($limit === null || $limit >= 1)
                    ->boolean();

                continue;
            }

            $limitText = $limit === null ? __('plans.unlimited') : (string) $limit;

            $entries[] = TextEntry::make($feature->value)
                ->label($feature->label())
                ->state($gate->tracksUsage($feature)
                    ? __('plans.usage', ['used' => $gate->usage($tenant, $feature), 'limit' => $limitText])
                    : $limitText);
        }

        return $entries;
    }

    /**
     * Timestamps are stored in UTC; the business has no timezone of its own
     * (locations do), so dates are shown in the primary market's timezone.
     */
    private function date(?CarbonImmutable $value): string
    {
        return $value === null
            ? '—'
            : $value->setTimezone('Europe/Istanbul')->locale(app()->getLocale())->isoFormat('LL');
    }

    /**
     * The most recent subscription, read through the tenant scope: the live
     * one, or the last ended (expired) one so its state can be explained.
     */
    private function subscription(): ?Subscription
    {
        if (! $this->loaded) {
            $this->subscription = Subscription::query()
                ->with('plan.translations')
                ->latest('id')
                ->first();
            $this->loaded = true;
        }

        return $this->subscription;
    }

    private function tenant(): Tenant
    {
        return app(TenantContext::class)->tenant();
    }
}
