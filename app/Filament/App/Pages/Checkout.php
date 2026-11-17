<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CheckoutNotAllowedException;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingCalculator;
use App\Services\Billing\BillingProfileService;
use App\Services\Billing\BillingQuote;
use App\Services\Billing\CheckoutEligibility;
use App\Services\Billing\CheckoutService;
use App\Support\MarketingUrl;
use App\Tenancy\TenantContext;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

/**
 * "Pay / renew": plan and billing period, the server-computed amount and the
 * billing details, then off to the provider's hosted payment page (3-D
 * Secure). Which plans and when is decided by CheckoutService; the amount
 * shown here is display only and recomputed when the checkout starts.
 * Owner-only (TenantPolicy::manageSettings), checked again in the service.
 */
class Checkout extends Page
{
    protected static ?string $slug = 'subscription/checkout';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Preselected plan code (e.g. from registration's "pay now"). */
    #[Url]
    public ?string $plan = null;

    private ?CheckoutEligibility $eligibility = null;

    public static function canAccess(): bool
    {
        $context = app(TenantContext::class);
        $user = auth()->user();

        return $context->check()
            && $user instanceof User
            && $user->can('manageSettings', $context->tenant());
    }

    public function getTitle(): string
    {
        return __('billing.checkout.title');
    }

    public function mount(): void
    {
        $plans = $this->plans();
        $current = $this->eligibility()->subscription?->plan;
        $selected = collect($plans)->first(fn (Plan $plan): bool => $plan->code === $this->plan)
            ?? collect($plans)->first(fn (Plan $plan): bool => $current !== null && $plan->is($current))
            ?? ($plans[0] ?? null);

        $this->form->fill([
            'plan' => $selected?->code,
            'interval' => $this->intervalsFor($selected)[0] ?? BillingInterval::Monthly->value,
            'terms' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $eligibility = $this->eligibility();
        $plans = $this->plans();
        $profile = app(BillingProfileService::class)->current();

        return $schema
            ->statePath('data')
            ->components([
                Callout::make(__('billing.checkout.not_open.title'))
                    ->description(fn (): ?string => $eligibility->allowed ? null : __('billing.errors.'.($eligibility->reason ?? 'not_allowed'), [
                        'date' => $eligibility->opensAt?->setTimezone('Europe/Istanbul')->locale(app()->getLocale())->isoFormat('LL'),
                    ]))
                    ->warning()
                    ->columnSpanFull()
                    ->visible(! $eligibility->allowed),
                Section::make(__('billing.checkout.sections.plan'))
                    ->description($eligibility->canChangePlan ? __('billing.checkout.sections.plan_help') : __('billing.checkout.sections.plan_fixed'))
                    ->columnSpanFull()
                    ->visible($eligibility->allowed && $plans !== [])
                    ->schema([
                        Radio::make('plan')
                            ->label(__('billing.checkout.fields.plan'))
                            ->options(collect($plans)->mapWithKeys(fn (Plan $plan): array => [$plan->code => $plan->translate()?->name ?? $plan->code])->all())
                            ->in(collect($plans)->pluck('code')->all())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                $intervals = $this->intervalsFor($this->planByCode($state));

                                if (! in_array($get('interval'), $intervals, true)) {
                                    $set('interval', $intervals[0] ?? null);
                                }
                            }),
                        Radio::make('interval')
                            ->label(__('billing.checkout.fields.interval'))
                            ->options(fn (Get $get): array => collect($this->intervalsFor($this->planByCode($get('plan'))))
                                ->mapWithKeys(fn (string $interval): array => [$interval => BillingInterval::from($interval)->label()])
                                ->all())
                            ->in(fn (Get $get): array => $this->intervalsFor($this->planByCode($get('plan'))))
                            ->inline()
                            ->required()
                            ->live(),
                    ]),
                Section::make(__('billing.checkout.sections.summary'))
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 2])
                    ->visible($eligibility->allowed && $plans !== [])
                    ->schema([
                        TextEntry::make('summary_base')
                            ->label(__('billing.checkout.summary.base'))
                            ->state(fn (Get $get): string => $this->quoteFor($get)?->base->format() ?? '—'),
                        TextEntry::make('summary_extra')
                            ->label(__('billing.checkout.summary.extra'))
                            ->state(fn (Get $get): string => ($quote = $this->quoteFor($get)) === null ? '—'
                                : __('billing.checkout.summary.extra_value', ['count' => $quote->extraLocations, 'amount' => $quote->extra->format()]))
                            ->visible(fn (Get $get): bool => ($this->quoteFor($get)?->extraLocations ?? 0) > 0),
                        TextEntry::make('summary_total')
                            ->label(__('billing.checkout.summary.total'))
                            ->state(fn (Get $get): string => $this->quoteFor($get)?->total->format() ?? '—')
                            ->helperText(__('billing.checkout.summary.vat_included'))
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('summary_period')
                            ->label(__('billing.checkout.summary.period'))
                            ->state(fn (Get $get): string => $this->periodText($get)),
                    ]),
                Section::make(__('billing.checkout.sections.billing'))
                    ->columnSpanFull()
                    ->visible($eligibility->allowed && $plans !== [])
                    ->headerActions([
                        Action::make('editBilling')
                            ->label($profile === null ? __('billing.checkout.billing.add') : __('billing.checkout.billing.edit'))
                            ->icon(Heroicon::OutlinedPencilSquare)
                            ->link()
                            ->url(fn (): string => ManageBillingProfile::getUrl(['return' => 'checkout'])),
                    ])
                    ->schema([
                        TextEntry::make('billing')
                            ->hiddenLabel()
                            ->state($profile === null
                                ? __('billing.checkout.billing.missing')
                                : collect([$profile->name, $profile->tax_office, $profile->address, trim(($profile->district ?? '').' / '.$profile->city, ' /'), $profile->email])->filter()->implode(' · '))
                            ->color($profile === null ? 'danger' : null),
                        Checkbox::make('terms')
                            ->label(new HtmlString(e(__('billing.checkout.fields.terms')).' <a href="'.e(MarketingUrl::to('terms')).'" target="_blank" rel="noopener" class="underline">'.e(__('billing.checkout.fields.terms_link')).'</a>'))
                            ->accepted()
                            ->validationMessages(['accepted' => __('billing.checkout.fields.terms_required')]),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        $payable = $this->eligibility()->allowed && $this->plans() !== [];

        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('pay')
                ->footer([
                    Actions::make(array_values(array_filter([
                        $payable ? Action::make('pay')
                            ->label(__('billing.checkout.pay'))
                            ->icon(Heroicon::OutlinedLockClosed)
                            ->disabled(app(BillingProfileService::class)->current() === null)
                            ->submit('pay') : null,
                        Action::make('back')
                            ->label(__('billing.actions.back_to_subscription'))
                            ->color('gray')
                            ->url(fn (): string => ViewSubscription::getUrl()),
                    ]))),
                    TextEntry::make('secure_note')
                        ->hiddenLabel()
                        ->state(__('billing.checkout.secure_note'))
                        ->color('gray')
                        ->visible($payable),
                ]),
        ]);
    }

    /**
     * Runs outside any database transaction: the payment row must survive a
     * provider error, and no lock is held during the HTTP call.
     */
    public function pay(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $plan = $this->planByCode($data['plan'] ?? null);
        $interval = BillingInterval::tryFrom((string) ($data['interval'] ?? ''));
        $user = auth()->user();

        if ($plan === null || $interval === null || ! $user instanceof User) {
            Notification::make()->danger()->title(__('billing.errors.plan_not_allowed'))->send();

            return;
        }

        try {
            $url = app(CheckoutService::class)->start($this->tenant(), $user, $plan, $interval, (string) request()->ip(), app()->getLocale());
        } catch (CheckoutNotAllowedException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        $this->redirect($url);
    }

    /**
     * @return list<Plan>
     */
    private function plans(): array
    {
        return app(CheckoutService::class)->payablePlans($this->eligibility());
    }

    private function eligibility(): CheckoutEligibility
    {
        return $this->eligibility ??= app(CheckoutService::class)->eligibility();
    }

    private function planByCode(?string $code): ?Plan
    {
        return collect($this->plans())->first(fn (Plan $plan): bool => $plan->code === $code);
    }

    /**
     * @return list<string>
     */
    private function intervalsFor(?Plan $plan): array
    {
        if ($plan === null) {
            return [];
        }

        return collect(BillingInterval::cases())
            ->filter(fn (BillingInterval $interval): bool => $plan->priceFor($interval) !== null)
            ->map(fn (BillingInterval $interval): string => $interval->value)
            ->values()
            ->all();
    }

    private function quoteFor(Get $get): ?BillingQuote
    {
        $plan = $this->planByCode($get('plan'));
        $interval = BillingInterval::tryFrom((string) $get('interval'));

        if ($plan === null || $interval === null || $plan->priceFor($interval) === null) {
            return null;
        }

        return app(BillingCalculator::class)->quote($plan, $interval);
    }

    private function periodText(Get $get): string
    {
        $interval = BillingInterval::tryFrom((string) $get('interval'));
        $subscription = $this->eligibility()->subscription;

        if ($interval === null) {
            return '—';
        }

        $now = now()->toImmutable();
        $startsAt = match (true) {
            $subscription?->status === SubscriptionStatus::Active && $subscription->current_period_ends_at?->isAfter($now) => $subscription->current_period_ends_at,
            $subscription?->status === SubscriptionStatus::Trialing && $subscription->trial_ends_at?->isAfter($now) => $subscription->trial_ends_at,
            default => $now,
        };
        $format = fn ($date): string => $date->setTimezone('Europe/Istanbul')->locale(app()->getLocale())->isoFormat('LL');

        return $format($startsAt).' – '.$format($startsAt->addMonthsNoOverflow($interval->months()));
    }

    private function tenant(): Tenant
    {
        return app(TenantContext::class)->tenant();
    }
}
