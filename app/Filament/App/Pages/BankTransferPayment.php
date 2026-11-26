<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\PaymentMethod;
use App\Filament\App\Support\PanelContext;
use App\Filament\App\Support\ServiceCall;
use App\Models\Payment;
use App\Services\Billing\BankTransferService;
use App\Services\Billing\PaymentMethods;
use App\Tenancy\TenantContext;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

/**
 * One bank transfer (havale/EFT, docs/architecture/subscriptions.md § Bank
 * transfers): the amount, the reference for the transfer description, the
 * platform's bank account and instructions (PaymentSetting), "I have paid"
 * and cancelling. The payment is read through the tenant scope; another
 * business' or a card payment is a 404. Owner-only, checked again in
 * BankTransferService.
 */
class BankTransferPayment extends Page
{
    protected static ?string $slug = 'payments/bank-transfer';

    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    #[Locked]
    public ?string $payment = null;

    private ?Payment $record = null;

    public static function canAccess(): bool
    {
        $context = app(TenantContext::class);
        $user = PanelContext::user();

        return $context->check() && $user !== null && $user->can('manageSettings', $context->tenant());
    }

    public function mount(): void
    {
        $this->record();
    }

    public function getTitle(): string
    {
        return __('billing.bank_transfer.title');
    }

    protected function getHeaderActions(): array
    {
        $payment = $this->record();

        return [
            Action::make('back')
                ->label($payment->isShopOrder() ? __('shop.orders.title') : __('billing.actions.back_to_subscription'))
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url($payment->isShopOrder() ? ShopOrders::getUrl() : ViewSubscription::getUrl()),
            Action::make('cancelTransfer')
                ->label(__('billing.bank_transfer.actions.cancel'))
                ->color('danger')
                ->outlined()
                ->visible(fn (): bool => $this->reportable())
                ->requiresConfirmation()
                ->modalDescription(__('billing.bank_transfer.actions.cancel_confirm'))
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);
                    ServiceCall::run(fn () => app(BankTransferService::class)->cancel(PanelContext::tenant(), PanelContext::user(), $this->record()), statePath: null);
                    Notification::make()->success()->title(__('billing.bank_transfer.actions.cancelled'))->send();
                    $this->record = null;
                }),
            Action::make('notify')
                ->label(__('billing.bank_transfer.actions.notify'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => $this->reportable())
                ->requiresConfirmation()
                ->modalHeading(__('billing.bank_transfer.actions.notify'))
                ->modalDescription(__('billing.bank_transfer.actions.notify_confirm', ['amount' => $payment->money()->format(), 'reference' => $payment->transfer_reference]))
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);
                    ServiceCall::run(fn () => app(BankTransferService::class)->markNotified(PanelContext::tenant(), PanelContext::user(), $this->record()), statePath: null);
                    Notification::make()->success()->title(__('billing.bank_transfer.actions.notified'))->send();
                    $this->record = null;
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $payment = $this->record();
        $settings = app(PaymentMethods::class)->settings();
        $tenant = PanelContext::tenant();
        $instructions = $settings->instructions(
            app()->getLocale(),
            (string) $payment->transfer_reference,
            $payment->money()->format(),
            $tenant->translate()?->name ?? $tenant->slug,
        );

        return $schema->components(array_values(array_filter([
            $this->statusCallout($payment),
            Section::make(__('billing.bank_transfer.sections.payment'))
                ->icon(Heroicon::OutlinedReceiptPercent)
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('amount')
                        ->label(__('billing.bank_transfer.amount'))
                        ->state($payment->money()->format())
                        ->helperText(__('billing.checkout.summary.vat_included'))
                        ->size(TextSize::Large)
                        ->weight(FontWeight::Bold)
                        ->copyable()
                        ->copyableState(number_format($payment->amount / 100, 2, ',', '.')),
                    TextEntry::make('reference')
                        ->label(__('billing.bank_transfer.reference'))
                        ->state($payment->transfer_reference)
                        ->helperText(__('billing.bank_transfer.reference_help'))
                        ->size(TextSize::Large)
                        ->weight(FontWeight::Bold)
                        ->fontFamily('mono')
                        ->copyable(),
                    TextEntry::make('purpose')
                        ->label(__('billing.bank_transfer.purpose'))
                        ->state($this->purpose($payment)),
                ]),
            $payment->isPending() ? Section::make(__('billing.bank_transfer.sections.account'))
                ->icon(Heroicon::OutlinedBuildingLibrary)
                ->columns(['default' => 1, 'md' => 2])
                ->schema(array_values(array_filter([
                    TextEntry::make('holder')
                        ->label(__('billing.settings.fields.bank_account_holder'))
                        ->state($settings->bank_account_holder ?? '—')
                        ->copyable(),
                    TextEntry::make('bank')
                        ->label(__('billing.settings.fields.bank_name'))
                        ->state($settings->bank_name)
                        ->placeholder('—'),
                    TextEntry::make('iban')
                        ->label(__('billing.settings.fields.bank_iban'))
                        ->state($settings->formattedIban() ?? '—')
                        ->fontFamily('mono')
                        ->weight(FontWeight::SemiBold)
                        ->copyable()
                        ->copyableState($settings->bank_iban)
                        ->columnSpanFull(),
                    $instructions !== null ? TextEntry::make('instructions')
                        ->label(__('billing.bank_transfer.instructions'))
                        // Admin-written plain text: escaped, line breaks kept.
                        ->state(new HtmlString(nl2br(e($instructions))))
                        ->columnSpanFull() : null,
                ]))) : null,
        ])));
    }

    private function statusCallout(Payment $payment): Callout
    {
        $state = match (true) {
            $payment->isSucceeded() => 'succeeded',
            $payment->isPending() && $payment->transfer_notified_at !== null => 'notified',
            $payment->isPending() => 'awaiting',
            $payment->failure_code === 'transfer_rejected' => 'rejected',
            default => 'cancelled',
        };

        $callout = Callout::make(__("billing.bank_transfer.states.{$state}.title"))
            ->description(__("billing.bank_transfer.states.{$state}.body", [
                'reference' => $payment->transfer_reference,
                'reason' => $payment->failure_message ?? '—',
            ]))
            ->columnSpanFull();

        if ($payment->isSucceeded()) {
            $callout->actions([
                Action::make('receipt')
                    ->label(__('billing.history.receipt'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->url(ViewReceipt::getUrl(['payment' => $payment->public_id])),
            ]);
        }

        return match ($state) {
            'succeeded' => $callout->success(),
            'awaiting' => $callout->info(),
            'notified' => $callout->warning(),
            default => $callout->danger(),
        };
    }

    private function purpose(Payment $payment): string
    {
        if ($payment->isShopOrder()) {
            return __('shop.payment_label').' · '.($payment->shopOrder?->order_number ?? '—');
        }

        $plan = $payment->plan?->translate()?->name ?? $payment->plan?->code ?? '—';

        return $payment->isUpgrade()
            ? __('billing.bank_transfer.admin.upgrade', ['plan' => $plan])
            : $plan.' · '.$payment->periodLabel();
    }

    /**
     * Not reported as paid yet: the owner can still report or cancel it.
     */
    private function reportable(): bool
    {
        $payment = $this->record();

        return $payment->isOpenBankTransfer() && $payment->transfer_notified_at === null;
    }

    private function record(): Payment
    {
        return $this->record ??= Payment::query()
            ->with(['plan.translations', 'shopOrder'])
            ->where('provider', PaymentMethod::BANK_TRANSFER_PROVIDER)
            ->where('public_id', (string) $this->payment)
            ->firstOr(fn () => abort(404));
    }
}
