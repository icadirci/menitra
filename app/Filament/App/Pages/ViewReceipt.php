<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

/**
 * Printable receipt of a successful payment (?payment={public_id}). Not an
 * invoice: the official e-Arşiv invoice is issued separately. The payment is
 * read through the tenant scope, so another tenant's id is a 404.
 * Owner-only (TenantPolicy::manageSettings).
 */
class ViewReceipt extends Page
{
    protected static ?string $slug = 'subscription/receipt';

    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    #[Locked]
    public string $payment = '';

    private ?Payment $record = null;

    public static function canAccess(): bool
    {
        $context = app(TenantContext::class);
        $user = auth()->user();

        return $context->check()
            && $user instanceof User
            && $user->can('manageSettings', $context->tenant());
    }

    public function mount(): void
    {
        $this->record();
    }

    public function getTitle(): string
    {
        return __('billing.receipt.title', ['number' => $this->record()->receipt_number]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label(__('billing.receipt.print'))
                ->icon(Heroicon::OutlinedPrinter)
                ->alpineClickHandler('window.print()'),
            Action::make('back')
                ->label(__('billing.actions.back_to_subscription'))
                ->color('gray')
                ->url(fn (): string => ViewSubscription::getUrl()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $payment = $this->record();
        $billing = $payment->billing_details;
        $date = fn (?CarbonImmutable $value): string => $value === null ? '—' : $value->setTimezone('Europe/Istanbul')->locale(app()->getLocale())->isoFormat('LL');

        return $schema->components([
            Section::make(__('billing.receipt.sections.payment'))
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('number')->label(__('billing.receipt.number'))->state($payment->receipt_number),
                    TextEntry::make('paid_at')->label(__('billing.receipt.paid_at'))->state($date($payment->paid_at)),
                    TextEntry::make('plan')->label(__('billing.receipt.plan'))
                        ->state(($payment->plan?->translate()?->name ?? $payment->plan?->code).' · '.$payment->interval->label()),
                    TextEntry::make('period')->label(__('billing.receipt.period'))
                        ->state($date($payment->period_starts_at).' – '.$date($payment->period_ends_at)),
                    TextEntry::make('card')->label(__('billing.receipt.card'))
                        ->state($payment->card_last_four !== null ? trim(($payment->card_association ?? '').' •••• '.$payment->card_last_four) : '—'),
                    TextEntry::make('reference')->label(__('billing.receipt.reference'))->state($payment->provider_payment_id ?? '—'),
                ]),
            Section::make(__('billing.receipt.sections.amount'))
                ->columns(['default' => 1, 'md' => 3])
                ->schema(array_values(array_filter([
                    TextEntry::make('base')->label(__('billing.checkout.summary.base'))->state($payment->baseMoney()->format()),
                    $payment->extra_locations > 0
                        ? TextEntry::make('extra')->label(__('billing.checkout.summary.extra'))
                            ->state(__('billing.checkout.summary.extra_value', ['count' => $payment->extra_locations, 'amount' => $payment->extraLocationsMoney()->format()]))
                        : null,
                    TextEntry::make('total')->label(__('billing.receipt.total'))->state($payment->money()->format())
                        ->helperText(__('billing.checkout.summary.vat_included'))->weight('bold'),
                ]))),
            Section::make(__('billing.receipt.sections.billing'))
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('billing_name')->label(__('billing.profile.fields.name'))->state($billing['name'] ?? '—'),
                    TextEntry::make('billing_tax')->label(__('billing.receipt.tax_id'))
                        ->state(trim(($billing['tax_office'] ?? '').' '.($billing['tax_id'] ?? ''))),
                    TextEntry::make('billing_address')->label(__('billing.profile.fields.address'))
                        ->state(collect([$billing['address'] ?? null, $billing['district'] ?? null, $billing['city'] ?? null, $billing['postal_code'] ?? null])->filter()->implode(', ')),
                    TextEntry::make('billing_email')->label(__('billing.profile.fields.email'))->state($billing['email'] ?? '—'),
                ]),
            Section::make()->schema([Text::make(__('billing.receipt.not_invoice'))]),
        ]);
    }

    private function record(): Payment
    {
        return $this->record ??= Payment::query()
            ->with('plan.translations')
            ->where('public_id', $this->payment)
            ->where('status', PaymentStatus::Succeeded)
            ->firstOr(fn () => abort(404));
    }
}
