<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
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

    protected string $view = 'filament.app.pages.view-receipt';

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

    /**
     * Receipt values, formatted on the server; the view only prints them.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $payment = $this->record();
        $billing = $payment->billing_details ?? [];
        $date = fn (?CarbonImmutable $value): string => $value === null ? '—' : $value->setTimezone('Europe/Istanbul')->locale(app()->getLocale())->isoFormat('LL');
        $planName = $payment->plan?->translate()?->name ?? $payment->plan?->code ?? '—';

        $lines = [[
            'label' => $payment->isUpgrade()
                ? __('billing.checkout.summary.upgrade_charge', ['days' => $payment->prorated_days])
                : $planName.' · '.$payment->periodLabel(),
            'detail' => $date($payment->period_starts_at).' – '.$date($payment->period_ends_at),
            'amount' => $payment->baseMoney()->format(),
        ]];

        if ($payment->extra_locations > 0) {
            $lines[] = [
                'label' => __('billing.checkout.summary.extra'),
                'detail' => __('billing.checkout.summary.extra_value', ['count' => $payment->extra_locations, 'amount' => $payment->extraLocationsMoney()->format()]),
                'amount' => $payment->extraLocationsMoney()->format(),
            ];
        }

        if ($payment->isUpgrade() && $payment->creditMoney() !== null) {
            $lines[] = [
                'label' => __('billing.checkout.summary.upgrade_credit'),
                'detail' => null,
                'amount' => '−'.$payment->creditMoney()->format(),
            ];
        }

        $taxLine = trim(($billing['tax_office'] ?? '').' '.($billing['tax_id'] ?? ''));

        return [
            'issuer' => [
                'name' => config('app.name'),
                'email' => SiteSetting::existing()?->contact_email,
            ],
            'number' => $payment->receipt_number,
            'status' => $payment->status->label(),
            'paidAt' => $date($payment->paid_at),
            'plan' => $planName.' · '.$payment->periodLabel(),
            'period' => $date($payment->period_starts_at).' – '.$date($payment->period_ends_at),
            'card' => $payment->card_last_four !== null ? trim(($payment->card_association ?? '').' •••• '.$payment->card_last_four) : null,
            'reference' => $payment->provider_payment_id,
            'billing' => [
                'name' => $billing['name'] ?? null,
                'tax' => $taxLine !== '' ? $taxLine : null,
                'address' => collect([$billing['address'] ?? null, $billing['district'] ?? null, $billing['city'] ?? null, $billing['postal_code'] ?? null])->filter()->implode(', ') ?: null,
                'email' => $billing['email'] ?? null,
            ],
            'lines' => $lines,
            'total' => $payment->money()->format(),
        ];
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
