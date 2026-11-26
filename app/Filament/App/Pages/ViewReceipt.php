<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\ShopOrderItem;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Money;
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
                ->label(fn (): string => $this->record()->isShopOrder() ? __('shop.actions.back_to_orders') : __('billing.actions.back_to_subscription'))
                ->color('gray')
                ->url(fn (): string => $this->record()->isShopOrder() ? ShopOrders::getUrl() : ViewSubscription::getUrl()),
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
            'detail' => $payment->isShopOrder() ? null : $date($payment->period_starts_at).' – '.$date($payment->period_ends_at),
            'amount' => $payment->baseMoney()->format(),
        ]];

        if ($payment->isShopOrder()) {
            $order = $payment->shopOrder()->with('items')->firstOrFail();
            $planName = __('shop.order_number').' '.$order->order_number;
            $lines = $order->items->map(fn (ShopOrderItem $item): array => [
                'label' => $item->product_name,
                'detail' => $item->quantity.' × '.Money::fromMinor($item->unit_amount, $order->currency)->format().($item->qr_label !== null ? ' · '.$item->qr_label : ''),
                'amount' => $item->total($order->currency)->format(),
            ])->all();

            if (! $order->shipping()->isZero()) {
                $lines[] = ['label' => __('shop.checkout.shipping'), 'detail' => null, 'amount' => $order->shipping()->format()];
            }
        }

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
            'plan' => $payment->isShopOrder() ? null : $planName.' · '.$payment->periodLabel(),
            'period' => $payment->isShopOrder() ? null : $date($payment->period_starts_at).' – '.$date($payment->period_ends_at),
            'order' => $payment->isShopOrder() ? $planName : null,
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
