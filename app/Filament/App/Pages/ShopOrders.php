<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\PaymentStatus;
use App\Enums\ShopOrderStatus;
use App\Models\Payment;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

/**
 * The business' shop orders with status and tracking, read through the tenant
 * scope. ?payment={public_id} (the provider's return) reports that payment.
 * Owner-only.
 */
class ShopOrders extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'shop/orders';

    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    #[Locked]
    public ?string $payment = null;

    public static function canAccess(): bool
    {
        return Shop::canAccess();
    }

    public function getTitle(): string
    {
        return __('shop.orders.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('shop.actions.back_to_shop'))
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(Shop::getUrl()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components(array_values(array_filter([
            $this->paymentResult(),
            EmbeddedTable::make(),
        ])));
    }

    public function table(Table $table): Table
    {
        return $table
            // Never-paid attempts older than a day are noise.
            ->query(ShopOrder::query()->with(['items', 'payment'])->where(fn (Builder $query) => $query
                ->whereIn('status', ShopOrderStatus::placed())
                ->orWhere('created_at', '>=', now()->subDay())))
            ->defaultSort('id', 'desc')
            ->paginated([10, 25])
            ->emptyStateHeading(__('shop.orders.empty'))
            ->columns([
                TextColumn::make('order_number')->label(__('shop.order_number')),
                TextColumn::make('created_at')
                    ->label(__('billing.history.columns.date'))
                    ->formatStateUsing(fn (ShopOrder $record): string => $this->date($record->paid_at ?? $record->created_at->toImmutable())),
                TextColumn::make('status')
                    ->label(__('billing.history.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (ShopOrder $record): string => $record->status->label())
                    ->color(fn (ShopOrder $record): string => $record->status->color()),
                TextColumn::make('total_amount')
                    ->label(__('billing.history.columns.amount'))
                    ->formatStateUsing(fn (ShopOrder $record): string => $record->total()->format()),
                TextColumn::make('tracking_number')
                    ->label(__('shop.tracking'))
                    ->placeholder('—')
                    ->formatStateUsing(fn (ShopOrder $record): string => trim($record->carrier.' '.$record->tracking_number)),
            ])
            ->recordActions([
                Action::make('details')
                    ->label(__('shop.actions.details'))
                    ->icon(Heroicon::OutlinedEye)
                    ->slideOver()
                    ->modalHeading(fn (ShopOrder $record): string => (string) $record->order_number)
                    ->modalSubmitAction(false)
                    ->schema(fn (ShopOrder $record): array => $this->details($record)),
                Action::make('track')
                    ->label(__('shop.track'))
                    ->icon(Heroicon::OutlinedTruck)
                    ->visible(fn (ShopOrder $record): bool => $record->tracking_url !== null)
                    ->url(fn (ShopOrder $record): ?string => $record->tracking_url, shouldOpenInNewTab: true),
                Action::make('receipt')
                    ->label(__('billing.history.receipt'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->visible(fn (ShopOrder $record): bool => $record->payment?->isSucceeded() === true)
                    ->url(fn (ShopOrder $record): string => ViewReceipt::getUrl(['payment' => $record->payment?->public_id])),
            ]);
    }

    /**
     * @return list<TextEntry>
     */
    private function details(ShopOrder $order): array
    {
        return array_values(array_filter([
            TextEntry::make('lines')
                ->label(__('shop.orders.items'))
                ->state($order->items->map(fn (ShopOrderItem $item): string => $item->summary().' — '.$item->total($order->currency)->format())->all())
                ->listWithLineBreaks(),
            TextEntry::make('shipping_text')->label(__('shop.checkout.shipping'))->state($order->shipping()->isZero() ? __('shop.free') : $order->shipping()->format()),
            TextEntry::make('total_text')->label(__('shop.checkout.total'))->state($order->total()->format()),
            TextEntry::make('address')->label(__('shop.checkout.address'))->state($order->addressLines())->listWithLineBreaks(),
            $order->customer_note !== null ? TextEntry::make('customer_note')->label(__('shop.checkout.note'))->state($order->customer_note) : null,
            $order->shipped_at !== null ? TextEntry::make('shipped')->label(__('shop.statuses.shipped'))->state($this->date($order->shipped_at)) : null,
            $order->cancel_reason !== null ? TextEntry::make('cancel')->label(__('shop.admin.orders.cancel_reason'))->state($order->cancel_reason) : null,
        ]));
    }

    private function paymentResult(): ?Callout
    {
        if ($this->payment === null || $this->payment === '') {
            return null;
        }

        $payment = Payment::query()->whereNotNull('shop_order_id')->where('public_id', $this->payment)->first();

        if ($payment === null) {
            return null;
        }

        $callout = Callout::make(__("shop.result.{$payment->status->value}.title"))
            ->description(__("shop.result.{$payment->status->value}.body"))
            ->columnSpanFull();

        return match ($payment->status) {
            PaymentStatus::Succeeded => $callout->success(),
            PaymentStatus::Pending => $callout->info(),
            default => $callout->danger()->actions([
                Action::make('retry')->label(__('shop.actions.retry'))->url(ShopCheckout::getUrl()),
            ]),
        };
    }

    private function date(CarbonImmutable $value): string
    {
        return $value->setTimezone('Europe/Istanbul')->locale(app()->getLocale())->isoFormat('LL');
    }
}
