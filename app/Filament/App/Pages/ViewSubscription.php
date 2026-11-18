<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\PaymentStatus;
use App\Enums\PlanFeature;
use App\Enums\SubscriptionStatus;
use App\Filament\App\Navigation\AppNavigationGroup;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\CheckoutService;
use App\Services\Subscription\FeatureGate;
use App\Tenancy\TenantContext;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Subscription overview: plan, status, key dates, plan limits with current
 * usage, the "pay / renew" entry (Checkout) and the payment history with
 * receipts. Nothing here changes a subscription: payments are applied by
 * PaymentProcessor through SubscriptionService. ?payment={public_id} (the
 * provider's return) shows that payment's outcome, read through the tenant
 * scope. Owner-only (TenantPolicy::manageSettings).
 */
class ViewSubscription extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = AppNavigationGroup::Business;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'subscription';

    /** The provider's return: which payment to report on. */
    #[Url]
    #[Locked]
    public ?string $payment = null;

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

    protected function getHeaderActions(): array
    {
        $eligibility = app(CheckoutService::class)->eligibility();

        return [
            Action::make('upgrade')
                ->label(__('billing.actions.upgrade'))
                ->icon(Heroicon::OutlinedArrowTrendingUp)
                ->url(Checkout::getUrl())
                ->visible($eligibility->canUpgrade && ! $eligibility->allowed),
            Action::make('pay')
                ->label($eligibility->subscription?->status === SubscriptionStatus::Active ? __('billing.actions.renew') : __('billing.actions.pay'))
                ->icon(Heroicon::OutlinedCreditCard)
                ->url(Checkout::getUrl())
                ->color($eligibility->canUpgrade && ! $eligibility->allowed ? 'gray' : 'primary')
                ->disabled(! $eligibility->allowed)
                ->tooltip($eligibility->allowed ? null : __('billing.errors.'.($eligibility->reason ?? 'not_allowed'), [
                    'date' => $eligibility->opensAt?->setTimezone('Europe/Istanbul')->locale(app()->getLocale())->isoFormat('LL'),
                ])),
            Action::make('billingDetails')
                ->label(__('billing.actions.billing_details'))
                ->icon(Heroicon::OutlinedIdentification)
                ->color('gray')
                ->url(ManageBillingProfile::getUrl()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Payment::query()->with('plan.translations')->whereIn('status', [PaymentStatus::Succeeded, PaymentStatus::Failed]))
            ->heading(__('billing.history.heading'))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->emptyStateHeading(__('billing.history.empty'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('billing.history.columns.date'))
                    ->formatStateUsing(fn (Payment $record): string => $this->date($record->paid_at ?? $record->failed_at ?? $record->created_at->toImmutable())),
                TextColumn::make('plan_id')
                    ->label(__('billing.history.columns.plan'))
                    ->formatStateUsing(fn (Payment $record): string => ($record->plan?->translate()?->name ?? $record->plan?->code).' · '.$record->interval->label()),
                TextColumn::make('amount')
                    ->label(__('billing.history.columns.amount'))
                    ->formatStateUsing(fn (Payment $record): string => $record->money()->format()),
                TextColumn::make('status')
                    ->label(__('billing.history.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (Payment $record): string => $record->status->label())
                    ->color(fn (Payment $record): string => $record->status->color()),
                TextColumn::make('receipt_number')
                    ->label(__('billing.history.columns.receipt'))
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('receipt')
                    ->label(__('billing.history.receipt'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->visible(fn (Payment $record): bool => $record->isSucceeded())
                    ->url(fn (Payment $record): string => ViewReceipt::getUrl(['payment' => $record->public_id])),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        $subscription = $this->subscription();
        $result = $this->paymentResult();

        if ($subscription === null) {
            return $schema->components(array_values(array_filter([
                $result,
                Section::make(__('panels.app.subscription.none.title'))
                    ->schema([Text::make(__('panels.app.subscription.none.body'))]),
                EmbeddedTable::make(),
            ])));
        }

        return $schema->components(array_values(array_filter([
            $result,
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
            EmbeddedTable::make(),
            Section::make()
                ->schema([Text::make(__('billing.subscription_note'))]),
        ])));
    }

    /**
     * The outcome of the payment the provider just returned from.
     */
    private function paymentResult(): ?Callout
    {
        if ($this->payment === null || $this->payment === '') {
            return null;
        }

        $payment = Payment::query()->where('public_id', $this->payment)->first();

        if ($payment === null) {
            return null;
        }

        $callout = Callout::make(__("billing.result.{$payment->status->value}.title"))
            ->description(__("billing.result.{$payment->status->value}.body", [
                'date' => $this->date($payment->period_ends_at),
            ]))
            ->columnSpanFull();

        return match ($payment->status) {
            PaymentStatus::Succeeded => $callout->success(),
            PaymentStatus::Pending => $callout->info(),
            default => $callout->danger(),
        };
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
