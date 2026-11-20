<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CheckoutNotAllowedException;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use App\Models\User;
use App\Services\Billing\BillingCalculator;
use App\Services\Billing\BillingProfileService;
use App\Services\Billing\BillingQuote;
use App\Services\Billing\CheckoutEligibility;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\UpgradeQuote;
use App\Services\Marketing\MarketingContent;
use App\Services\Marketing\PricingPlan;
use App\Support\MarketingUrl;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

/**
 * Pay / renew / upgrade (docs/architecture/subscriptions.md § Payments).
 *
 * Billing period toggle with the yearly advantage, plan cards (price, the
 * per-month equivalent and saving of yearly billing, plan highlights, the
 * prorated amount of an upgrade), billing details and an order summary,
 * then off to the provider's hosted payment page (3-D Secure).
 *
 * What can be bought and the amount are decided by CheckoutService and
 * BillingCalculator; everything shown here is display only and recomputed
 * when the checkout starts. Owner-only (TenantPolicy::manageSettings),
 * checked again in the service.
 */
class Checkout extends Page
{
    protected static ?string $slug = 'subscription/checkout';

    protected static bool $shouldRegisterNavigation = false;

    protected Width|string|null $maxContentWidth = Width::SevenExtraLarge;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Preselected plan code (registration's "pay now", the pricing page). */
    #[Url]
    public ?string $plan = null;

    private ?CheckoutEligibility $eligibility = null;

    /** @var list<Plan>|null */
    private ?array $plans = null;

    /** @var array<string, PricingPlan|null> */
    private array $display = [];

    /** @var array<string, UpgradeQuote> */
    private array $upgrades = [];

    private ?BillingCalculator $calculator = null;

    private TenantBillingProfile|false|null $profile = false;

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
        return $this->eligibility()->isActivePeriod() && ! $this->eligibility()->allowed
            ? __('billing.checkout.title_upgrade')
            : __('billing.checkout.title');
    }

    public function getSubheading(): ?string
    {
        return __('billing.checkout.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('billing.actions.back_to_subscription'))
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => ViewSubscription::getUrl()),
        ];
    }

    public function mount(): void
    {
        $plans = $this->plans();
        $eligibility = $this->eligibility();
        $current = $eligibility->subscription?->plan;
        $selected = collect($plans)->first(fn (Plan $plan): bool => $plan->code === $this->plan)
            ?? collect($plans)->first(fn (Plan $plan): bool => $current !== null && $plan->is($current))
            ?? collect($plans)->first(fn (Plan $plan): bool => $plan->is_featured)
            ?? ($plans[0] ?? null);

        $intervals = $this->intervalsFor($selected);
        $preferred = $eligibility->fixedInterval?->value ?? BillingInterval::Yearly->value;

        $this->form->fill([
            'plan' => $selected?->code,
            'interval' => in_array($preferred, $intervals, true) ? $preferred : ($intervals[0] ?? BillingInterval::Monthly->value),
            'terms' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $eligibility = $this->eligibility();
        $plans = $this->plans();
        $payable = $eligibility->canPayAnything() && $plans !== [];

        return $schema
            ->statePath('data')
            ->components([
                Hidden::make('plan')
                    ->required()
                    ->in(collect($plans)->pluck('code')->all()),
                Callout::make(__('billing.checkout.not_open.title'))
                    ->description(__('billing.errors.'.($eligibility->reason ?? 'not_allowed'), ['date' => $this->date($eligibility->opensAt)]))
                    ->warning()
                    ->columnSpanFull()
                    ->visible(! $payable),
                $this->activePeriodCallout()?->visible($payable),
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->visible($payable)
                    ->schema([
                        Group::make([
                            $this->intervalSection(),
                            $this->plansSection(),
                            $this->billingSection(),
                        ])->columnSpan(['default' => 1, 'lg' => 2]),
                        Group::make([
                            $this->summarySection(),
                        ])->columnSpan(['default' => 1, 'lg' => 1]),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('pay'),
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
        // An upgrade hides the interval field (hidden fields aren't in the state); it keeps the period's interval.
        $interval = $plan !== null && $this->isUpgrade($plan->code)
            ? $this->eligibility()->fixedInterval
            : BillingInterval::tryFrom((string) ($data['interval'] ?? ''));
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

    private function activePeriodCallout(): ?Callout
    {
        $eligibility = $this->eligibility();
        $subscription = $eligibility->subscription;

        if (! $eligibility->isActivePeriod() || $subscription === null) {
            return null;
        }

        $body = collect([
            $eligibility->canUpgrade ? __('billing.checkout.active.upgrade_help') : null,
            $eligibility->allowed
                ? __('billing.checkout.active.renewal_open')
                : __('billing.errors.renewal_not_open', ['date' => $this->date($eligibility->opensAt)]),
        ])->filter()->implode(' ');

        return Callout::make(__('billing.checkout.active.title', [
            'plan' => $subscription->plan?->translate()?->name ?? $subscription->plan?->code,
            'interval' => $eligibility->fixedInterval?->label(),
            'date' => $this->date($subscription->current_period_ends_at),
        ]))
            ->description($body)
            ->icon(Heroicon::OutlinedInformationCircle)
            ->info()
            ->columnSpanFull();
    }

    private function intervalSection(): Section
    {
        return Section::make(__('billing.checkout.sections.interval'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->schema([
                ToggleButtons::make('interval')
                    ->hiddenLabel()
                    ->options(fn (Get $get): array => collect(BillingInterval::cases())
                        ->mapWithKeys(fn (BillingInterval $interval): array => [$interval->value => $this->intervalLabel($interval, $this->planByCode($get('plan')))])
                        ->all())
                    ->icons([
                        BillingInterval::Monthly->value => Heroicon::OutlinedCalendar,
                        BillingInterval::Yearly->value => Heroicon::OutlinedSparkles,
                    ])
                    ->disableOptionWhen(fn (string $value, Get $get): bool => ! in_array($value, $this->intervalsFor($this->planByCode($get('plan'))), true))
                    ->in(fn (Get $get): array => $this->intervalsFor($this->planByCode($get('plan'))))
                    ->grouped()
                    ->required()
                    ->live()
                    ->visible(fn (Get $get): bool => ! $this->isUpgrade($get('plan'))),
                Text::make(fn (Get $get): ?string => $this->yearlyPerkText($this->planByCode($get('plan'))))
                    ->icon(Heroicon::OutlinedGift)
                    ->color('success')
                    ->weight(FontWeight::Medium)
                    ->visible(fn (Get $get): bool => ! $this->isUpgrade($get('plan')) && $this->yearlyPerkText($this->planByCode($get('plan'))) !== null),
                Text::make(fn (): string => __('billing.checkout.upgrade_interval', ['interval' => $this->eligibility()->fixedInterval?->label()]))
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->color('gray')
                    ->visible(fn (Get $get): bool => $this->isUpgrade($get('plan'))),
            ]);
    }

    private function plansSection(): Section
    {
        return Section::make(__('billing.checkout.sections.plan'))
            ->description($this->eligibility()->isActivePeriod() ? __('billing.checkout.sections.plan_active') : __('billing.checkout.sections.plan_help'))
            ->icon(Heroicon::OutlinedSquares2x2)
            ->schema([
                View::make('filament.app.checkout.plan-cards')
                    ->viewData(fn (Get $get): array => [
                        'cards' => array_map(fn (Plan $plan): array => $this->planCard($plan, $get('plan'), $get('interval')), $this->plans()),
                        'label' => __('billing.checkout.sections.plan'),
                    ]),
            ]);
    }

    /**
     * Selecting a card (click / Enter / Space). The code is re-checked
     * against the payable plans; the interval falls back to one the plan has.
     */
    public function selectPlan(string $code): void
    {
        abort_unless(static::canAccess(), 403);

        $plan = $this->planByCode($code);

        if ($plan === null) {
            return;
        }

        $this->data['plan'] = $plan->code;
        $intervals = $this->intervalsFor($plan);

        if (! in_array($this->data['interval'] ?? null, $intervals, true)) {
            $this->data['interval'] = $intervals[0] ?? null;
        }
    }

    /**
     * Display data of one plan card (escaped in the view).
     *
     * @return array<string, mixed>
     */
    private function planCard(Plan $plan, ?string $selected, ?string $interval): array
    {
        $display = $this->display($plan);
        $current = $this->eligibility()->subscription?->plan;
        $isCurrent = $current !== null && $plan->is($current);
        $isUpgrade = $this->isUpgrade($plan->code);
        $yearly = $interval === BillingInterval::Yearly->value && $display?->yearly !== null;

        return [
            'code' => $plan->code,
            'name' => $display?->name ?? $plan->code,
            'description' => $display?->description,
            'selected' => $selected === $plan->code,
            'badge' => match (true) {
                $isCurrent => ['label' => __('billing.checkout.badges.current'), 'tone' => 'gray'],
                $isUpgrade => ['label' => __('billing.checkout.badges.upgrade'), 'tone' => 'success'],
                $plan->is_featured => ['label' => __('billing.checkout.badges.popular'), 'tone' => 'primary'],
                default => null,
            },
            'price' => ($yearly ? $display->yearly : $display?->monthly)?->format(omitZeroFraction: true) ?? '—',
            'suffix' => $yearly ? __('billing.checkout.per_year') : __('billing.checkout.per_month'),
            'note' => $this->priceNote($plan, $interval),
            'saving' => $this->savingText($plan, $interval),
            'upgrade' => $isUpgrade ? __('billing.checkout.upgrade_today', [
                'amount' => $this->upgradeQuote($plan)->total->format(omitZeroFraction: true),
                'days' => $this->upgradeQuote($plan)->remainingDays,
            ]) : null,
            'highlights' => $display?->highlights() ?? [],
            'branch' => $this->branchNote($display),
            'action' => $selected === $plan->code ? __('billing.checkout.selected') : __('billing.checkout.select'),
        ];
    }

    private function billingSection(): Section
    {
        $profile = $this->profile();

        return Section::make(__('billing.checkout.sections.billing'))
            ->icon(Heroicon::OutlinedIdentification)
            ->headerActions([
                Action::make('editBilling')
                    ->label($profile === null ? __('billing.checkout.billing.add') : __('billing.checkout.billing.edit'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->link()
                    ->url(fn (): string => ManageBillingProfile::getUrl(['return' => 'checkout'])),
            ])
            ->schema($profile === null ? [
                Callout::make(__('billing.checkout.billing.missing'))
                    ->warning()
                    ->actions([
                        Action::make('addBilling')
                            ->label(__('billing.checkout.billing.add'))
                            ->url(fn (): string => ManageBillingProfile::getUrl(['return' => 'checkout'])),
                    ]),
            ] : [
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    TextEntry::make('billing_name')
                        ->label($profile->type->label())
                        ->state($profile->name),
                    TextEntry::make('billing_tax')
                        ->label(__('billing.receipt.tax_id'))
                        ->state(trim(($profile->tax_office ?? '').' '.$this->masked((string) $profile->tax_id))),
                    TextEntry::make('billing_address')
                        ->label(__('billing.profile.fields.address'))
                        ->state(collect([$profile->address, $profile->district, $profile->city])->filter()->implode(', ')),
                    TextEntry::make('billing_email')
                        ->label(__('billing.profile.fields.email'))
                        ->state($profile->email),
                ]),
            ]);
    }

    private function summarySection(): Section
    {
        return Section::make(__('billing.checkout.sections.summary'))
            ->icon(Heroicon::OutlinedReceiptPercent)
            ->schema([
                Text::make(fn (Get $get): string => $this->summaryTitle($get))
                    ->weight(FontWeight::SemiBold),
                TextEntry::make('summary_base')
                    ->label(__('billing.checkout.summary.base'))
                    ->inlineLabel()
                    ->state(fn (Get $get): string => $this->quoteFor($get)?->base->format() ?? '—')
                    ->visible(fn (Get $get): bool => ! $this->isUpgrade($get('plan'))),
                TextEntry::make('summary_extra')
                    ->label(__('billing.checkout.summary.extra'))
                    ->inlineLabel()
                    ->state(fn (Get $get): string => ($quote = $this->quoteFor($get)) === null ? '—'
                        : __('billing.checkout.summary.extra_value', ['count' => $quote->extraLocations, 'amount' => $quote->extra->format()]))
                    ->visible(fn (Get $get): bool => ! $this->isUpgrade($get('plan')) && ($this->quoteFor($get)?->extraLocations ?? 0) > 0),
                TextEntry::make('summary_upgrade_charge')
                    ->label(fn (Get $get): string => __('billing.checkout.summary.upgrade_charge', ['days' => $this->upgradeFor($get)?->remainingDays]))
                    ->inlineLabel()
                    ->state(fn (Get $get): string => $this->upgradeFor($get)?->charge->format() ?? '—')
                    ->visible(fn (Get $get): bool => $this->isUpgrade($get('plan'))),
                TextEntry::make('summary_upgrade_credit')
                    ->label(__('billing.checkout.summary.upgrade_credit'))
                    ->inlineLabel()
                    ->state(fn (Get $get): string => '−'.($this->upgradeFor($get)?->credit->format() ?? '—'))
                    ->color('success')
                    ->visible(fn (Get $get): bool => $this->isUpgrade($get('plan'))),
                TextEntry::make('summary_total')
                    ->label(__('billing.checkout.summary.total'))
                    ->inlineLabel()
                    ->state(fn (Get $get): string => $this->totalFor($get)?->format() ?? '—')
                    ->helperText(__('billing.checkout.summary.vat_included'))
                    ->size(TextSize::Large)
                    ->weight(FontWeight::Bold),
                Text::make(fn (Get $get): ?string => $this->summarySaving($get))
                    ->icon(Heroicon::OutlinedGift)
                    ->color('success')
                    ->weight(FontWeight::Medium)
                    ->visible(fn (Get $get): bool => $this->summarySaving($get) !== null),
                Callout::make(fn (Get $get): ?string => $this->switchToYearlyText($get))
                    ->icon(Heroicon::OutlinedSparkles)
                    ->info()
                    ->actions([
                        Action::make('switchYearly')
                            ->label(__('billing.checkout.switch_yearly'))
                            ->action(fn (Set $set) => $set('interval', BillingInterval::Yearly->value)),
                    ])
                    ->visible(fn (Get $get): bool => $this->switchToYearlyText($get) !== null),
                TextEntry::make('summary_period')
                    ->label(__('billing.checkout.summary.period'))
                    ->state(fn (Get $get): string => $this->periodText($get))
                    ->icon(Heroicon::OutlinedCalendarDays),
                Checkbox::make('terms')
                    ->label(new HtmlString(e(__('billing.checkout.fields.terms')).' <a href="'.e(MarketingUrl::to('terms')).'" target="_blank" rel="noopener" class="underline">'.e(__('billing.checkout.fields.terms_link')).'</a>'))
                    ->accepted()
                    ->validationMessages(['accepted' => __('billing.checkout.fields.terms_required')]),
                Actions::make([
                    Action::make('pay')
                        ->label(fn (Get $get): string => __('billing.checkout.pay_amount', ['amount' => $this->totalFor($get)?->format() ?? '']))
                        ->icon(Heroicon::LockClosed)
                        ->size('lg')
                        ->disabled($this->profile() === null)
                        ->submit('pay'),
                ])->fullWidth(),
                Group::make([
                    Text::make(__('billing.checkout.trust.secure'))->icon(Heroicon::OutlinedShieldCheck)->color('gray')->size(TextSize::Small),
                    Text::make(__('billing.checkout.trust.card'))->icon(Heroicon::OutlinedCreditCard)->color('gray')->size(TextSize::Small),
                    Text::make(__('billing.checkout.trust.receipt'))->icon(Heroicon::OutlinedEnvelope)->color('gray')->size(TextSize::Small),
                    Text::make(__('billing.checkout.trust.no_auto'))->icon(Heroicon::OutlinedArrowPath)->color('gray')->size(TextSize::Small),
                ])->dense(),
            ]);
    }

    private function intervalLabel(BillingInterval $interval, ?Plan $plan): string
    {
        $saving = $interval === BillingInterval::Yearly ? $this->display($plan)?->yearlySavingPercent : null;

        return $saving !== null
            ? __('billing.checkout.interval_yearly_saving', ['interval' => $interval->label(), 'percent' => $saving])
            : $interval->label();
    }

    /**
     * "Pay 8 months instead of 12 – 4 months free" for the selected plan.
     */
    private function yearlyPerkText(?Plan $plan): ?string
    {
        $display = $this->display($plan);

        if ($display?->yearly === null || $display->monthly->isZero() || $display->yearlySavingPercent === null) {
            return null;
        }

        $paidMonths = $display->yearly->minor / $display->monthly->minor;
        $free = (int) round(12 - $paidMonths);

        return $free >= 1 && abs($paidMonths - round($paidMonths)) < 0.01
            ? trans_choice('billing.checkout.yearly_perk_months', $free, ['paid' => (int) round($paidMonths), 'free' => $free])
            : __('billing.checkout.yearly_perk_percent', ['percent' => $display->yearlySavingPercent]);
    }

    private function priceNote(Plan $plan, ?string $interval): ?string
    {
        $display = $this->display($plan);

        if ($interval === BillingInterval::Yearly->value && $display?->yearlyPerMonth !== null) {
            return __('billing.checkout.per_month_equivalent', ['amount' => $display->yearlyPerMonth->format(omitZeroFraction: true)]);
        }

        if ($interval === BillingInterval::Yearly->value && $display !== null && $display->yearly === null) {
            return __('billing.checkout.monthly_only');
        }

        return null;
    }

    private function savingText(Plan $plan, ?string $interval): ?string
    {
        $display = $this->display($plan);

        if ($interval !== BillingInterval::Yearly->value || $display?->yearly === null || $display->yearlySavingPercent === null) {
            return null;
        }

        return __('billing.checkout.saving', [
            'amount' => $this->yearlySaving($display)->format(omitZeroFraction: true),
            'percent' => $display->yearlySavingPercent,
        ]);
    }

    private function branchNote(?PricingPlan $display): ?string
    {
        if ($display === null || ! $display->hasLocationPricing()) {
            return null;
        }

        return __('billing.checkout.branch_note', [
            'count' => $display->includedLocations,
            'amount' => $display->extraLocationMonthly?->format(omitZeroFraction: true),
        ]);
    }

    private function summaryTitle(Get $get): string
    {
        $plan = $this->planByCode($get('plan'));
        $name = $this->display($plan)?->name ?? $plan?->code ?? '—';

        if ($this->isUpgrade($get('plan'))) {
            return __('billing.checkout.summary.upgrade_title', ['plan' => $name]);
        }

        $interval = BillingInterval::tryFrom((string) $get('interval'));

        return $interval === null ? $name : "{$name} · {$interval->label()}";
    }

    private function summarySaving(Get $get): ?string
    {
        if ($this->isUpgrade($get('plan')) || $get('interval') !== BillingInterval::Yearly->value) {
            return null;
        }

        $display = $this->display($this->planByCode($get('plan')));

        if ($display?->yearly === null || $display->yearlySavingPercent === null) {
            return null;
        }

        return __('billing.checkout.summary.saving', [
            'amount' => $this->yearlySaving($display)->format(omitZeroFraction: true),
            'percent' => $display->yearlySavingPercent,
            'monthly' => $display->yearlyPerMonth?->format(omitZeroFraction: true),
        ]);
    }

    private function switchToYearlyText(Get $get): ?string
    {
        if ($this->isUpgrade($get('plan')) || $get('interval') !== BillingInterval::Monthly->value) {
            return null;
        }

        $display = $this->display($this->planByCode($get('plan')));

        if ($display?->yearly === null || $display->yearlySavingPercent === null) {
            return null;
        }

        return __('billing.checkout.switch_yearly_hint', [
            'amount' => $this->yearlySaving($display)->format(omitZeroFraction: true),
            'percent' => $display->yearlySavingPercent,
        ]);
    }

    private function yearlySaving(PricingPlan $display): Money
    {
        return Money::fromMinor($display->monthly->minor * 12 - (int) $display->yearly?->minor, $display->monthly->currency);
    }

    private function totalFor(Get $get): ?Money
    {
        return $this->isUpgrade($get('plan'))
            ? $this->upgradeFor($get)?->total
            : $this->quoteFor($get)?->total;
    }

    private function quoteFor(Get $get): ?BillingQuote
    {
        $plan = $this->planByCode($get('plan'));
        $interval = BillingInterval::tryFrom((string) $get('interval'));

        if ($plan === null || $interval === null || $plan->priceFor($interval) === null) {
            return null;
        }

        return $this->calculator()->quote($plan, $interval);
    }

    private function upgradeFor(Get $get): ?UpgradeQuote
    {
        $plan = $this->planByCode($get('plan'));

        return $plan !== null && $this->isUpgrade($plan->code) ? $this->upgradeQuote($plan) : null;
    }

    private function upgradeQuote(Plan $plan): UpgradeQuote
    {
        return $this->upgrades[$plan->code] ??= $this->calculator()->upgradeQuote($this->eligibility()->subscription, $plan);
    }

    private function periodText(Get $get): string
    {
        if ($this->isUpgrade($get('plan'))) {
            return __('billing.checkout.summary.upgrade_period', ['date' => $this->date($this->eligibility()->subscription?->current_period_ends_at)]);
        }

        $interval = BillingInterval::tryFrom((string) $get('interval'));
        $subscription = $this->eligibility()->subscription;

        if ($interval === null) {
            return '—';
        }

        $now = CarbonImmutable::now();
        $startsAt = match (true) {
            $this->eligibility()->isActivePeriod() && $subscription?->current_period_ends_at?->isAfter($now) => $subscription->current_period_ends_at,
            $subscription?->status === SubscriptionStatus::Trialing && $subscription->trial_ends_at?->isAfter($now) => $subscription->trial_ends_at,
            default => $now,
        };

        return $this->date($startsAt).' – '.$this->date($startsAt->addMonthsNoOverflow($interval->months()));
    }

    private function isUpgrade(?string $code): bool
    {
        $plan = $this->planByCode($code);

        return $plan !== null && app(CheckoutService::class)->isUpgrade($this->eligibility(), $plan);
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

    private function display(?Plan $plan): ?PricingPlan
    {
        if ($plan === null) {
            return null;
        }

        if (! array_key_exists($plan->code, $this->display)) {
            $this->display[$plan->code] = app(MarketingContent::class)->pricingPlan($plan->loadMissing(['translations', 'prices', 'limits']));
        }

        return $this->display[$plan->code];
    }

    /**
     * @return list<Plan>
     */
    private function plans(): array
    {
        return $this->plans ??= app(CheckoutService::class)->payablePlans($this->eligibility());
    }

    private function eligibility(): CheckoutEligibility
    {
        return $this->eligibility ??= app(CheckoutService::class)->eligibility();
    }

    private function planByCode(?string $code): ?Plan
    {
        return collect($this->plans())->first(fn (Plan $plan): bool => $plan->code === $code);
    }

    private function calculator(): BillingCalculator
    {
        return $this->calculator ??= app(BillingCalculator::class);
    }

    private function profile(): ?TenantBillingProfile
    {
        if ($this->profile === false) {
            $this->profile = app(BillingProfileService::class)->current();
        }

        return $this->profile;
    }

    private function masked(string $value): string
    {
        return strlen($value) <= 4 ? $value : str_repeat('•', strlen($value) - 4).substr($value, -4);
    }

    private function date(?CarbonImmutable $value): string
    {
        return $value === null ? '—' : $value->setTimezone('Europe/Istanbul')->locale(app()->getLocale())->isoFormat('LL');
    }

    private function tenant(): Tenant
    {
        return app(TenantContext::class)->tenant();
    }
}
