<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ShopOrders;

use App\Enums\ShopOrderStatus;
use App\Filament\Admin\Navigation\AdminNavigationGroup;
use App\Filament\Admin\Resources\ShopOrders\Pages\ListShopOrders;
use App\Filament\App\Support\ServiceCall;
use App\Models\QrCode;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Admin\ShopOrderAdminService;
use App\Services\QrCodes\QrCodeImage;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;
use ZipArchive;

/**
 * Orders of the QR stand shop: list, details (delivery address), printable QR
 * files and the fulfilment steps. List page only; a Livewire page holding the
 * tenant-owned record would reload it through the fail-closed scope. Reads and
 * writes go through ShopOrderAdminService.
 */
class ShopOrderResource extends Resource
{
    protected static ?string $model = ShopOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Shop;

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('shop.admin.orders.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('shop.admin.orders.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return app(ShopOrderAdminService::class)->query();
    }

    /**
     * Paid orders waiting to be handled.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', ShopOrderStatus::Paid)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label(__('shop.order_number'))
                    ->searchable(),
                TextColumn::make('tenant_id')
                    ->label(__('admin.tenants.singular'))
                    ->formatStateUsing(fn (ShopOrder $record): string => $record->tenant?->translate($record->tenant->default_locale)?->name ?? $record->tenant?->slug ?? '—'),
                TextColumn::make('status')
                    ->label(__('admin.tenants.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (ShopOrder $record): string => $record->status->label())
                    ->color(fn (ShopOrder $record): string => $record->status->color())
                    ->description(fn (ShopOrder $record): ?string => match (true) {
                        $record->status !== ShopOrderStatus::Cancelled => null,
                        $record->refunded_at !== null => __('shop.admin.orders.refunded'),
                        default => __('shop.admin.orders.refund_due'),
                    }),
                TextColumn::make('total_amount')
                    ->label(__('shop.checkout.total'))
                    ->formatStateUsing(fn (ShopOrder $record): string => $record->total()->format()),
                TextColumn::make('paid_at')
                    ->label(__('billing.receipt.paid_at'))
                    ->dateTime(timezone: 'Europe/Istanbul')
                    ->placeholder('—'),
                TextColumn::make('tracking_number')
                    ->label(__('shop.tracking'))
                    ->placeholder('—'),
            ])
            ->filters([
                // Unpaid attempts are hidden until chosen.
                SelectFilter::make('status')
                    ->label(__('admin.tenants.fields.status'))
                    ->multiple()
                    ->default(array_map(fn (ShopOrderStatus $status): string => $status->value, ShopOrderStatus::placed()))
                    ->options(collect(ShopOrderStatus::cases())->mapWithKeys(fn (ShopOrderStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->recordActions([
                Action::make('details')
                    ->label(__('shop.actions.details'))
                    ->icon(Heroicon::OutlinedEye)
                    ->authorize('view')
                    ->slideOver()
                    ->modalHeading(fn (ShopOrder $record): string => (string) $record->order_number)
                    ->modalSubmitAction(false)
                    ->schema(fn (ShopOrder $record): array => self::details($record)),
                ActionGroup::make([
                    Action::make('qrFiles')
                        ->label(__('shop.admin.orders.qr_files'))
                        ->icon(Heroicon::OutlinedQrCode)
                        ->authorize('view')
                        ->visible(fn (ShopOrder $record): bool => in_array($record->status, [ShopOrderStatus::Paid, ShopOrderStatus::Preparing, ShopOrderStatus::Shipped], true))
                        ->action(fn (ShopOrder $record): StreamedResponse => self::qrArchive($record)),
                    self::change('prepare', ShopOrderStatus::Preparing)
                        ->requiresConfirmation()
                        ->action(fn (ShopOrder $record, Action $action) => self::run($action, fn (ShopOrderAdminService $service, User $actor) => $service->prepare($actor, $record))),
                    self::change('ship', ShopOrderStatus::Shipped)
                        ->schema([
                            TextInput::make('carrier')->label(__('shop.admin.orders.carrier'))->required()->maxLength(60),
                            TextInput::make('tracking_number')->label(__('shop.admin.orders.tracking_number'))->maxLength(60),
                            TextInput::make('tracking_url')->label(__('shop.admin.orders.tracking_url'))->url()->rule('starts_with:https://')->maxLength(255),
                        ])
                        ->action(fn (ShopOrder $record, array $data, Action $action) => self::run($action, fn (ShopOrderAdminService $service, User $actor) => $service->ship($actor, $record, (string) $data['carrier'], $data['tracking_number'] ?? null, $data['tracking_url'] ?? null))),
                    self::change('deliver', ShopOrderStatus::Delivered)
                        ->requiresConfirmation()
                        ->action(fn (ShopOrder $record, Action $action) => self::run($action, fn (ShopOrderAdminService $service, User $actor) => $service->deliver($actor, $record))),
                    self::change('cancel', ShopOrderStatus::Cancelled)
                        ->color('danger')
                        ->modalDescription(__('shop.admin.orders.cancel_confirm'))
                        ->schema([
                            Textarea::make('reason')->label(__('shop.admin.orders.cancel_reason'))->helperText(__('shop.admin.orders.cancel_reason_help'))->required()->rows(2)->maxLength(500),
                        ])
                        ->action(fn (ShopOrder $record, array $data, Action $action) => self::run($action, fn (ShopOrderAdminService $service, User $actor) => $service->cancel($actor, $record, (string) $data['reason']))),
                    Action::make('refunded')
                        ->label(__('shop.admin.orders.actions.refunded'))
                        ->authorize('manage')
                        ->visible(fn (ShopOrder $record): bool => $record->status === ShopOrderStatus::Cancelled && $record->refunded_at === null)
                        ->requiresConfirmation()
                        ->modalDescription(__('shop.admin.orders.refund_confirm'))
                        ->successNotificationTitle(__('admin.notifications.saved'))
                        ->action(fn (ShopOrder $record, Action $action) => self::run($action, fn (ShopOrderAdminService $service, User $actor) => $service->markRefunded($actor, $record))),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShopOrders::route('/'),
        ];
    }

    private static function change(string $name, ShopOrderStatus $to): Action
    {
        return Action::make($name)
            ->label(__("shop.admin.orders.actions.{$name}"))
            ->authorize('manage')
            ->visible(fn (ShopOrder $record): bool => $record->status->canBecome($to))
            ->successNotificationTitle(__('admin.notifications.saved'));
    }

    /**
     * @param  Closure(ShopOrderAdminService, User): void  $change
     */
    private static function run(Action $action, Closure $change): void
    {
        /** @var User $actor */
        $actor = auth()->user();

        ServiceCall::run(fn () => $change(app(ShopOrderAdminService::class), $actor), statePath: null);
        $action->success();
    }

    /**
     * @return list<TextEntry>
     */
    private static function details(ShopOrder $order): array
    {
        $service = app(ShopOrderAdminService::class);
        $payment = $service->payment($order);

        return [
            TextEntry::make('lines')
                ->label(__('shop.orders.items'))
                ->state($service->items($order)->map(fn (ShopOrderItem $item): string => $item->summary())->all())
                ->listWithLineBreaks(),
            TextEntry::make('amounts')
                ->label(__('shop.checkout.total'))
                ->state($order->subtotal()->format().' + '.$order->shipping()->format().' = '.$order->total()->format()),
            TextEntry::make('address')
                ->label(__('shop.checkout.address'))
                ->state($order->addressLines())
                ->listWithLineBreaks()
                ->copyable()
                ->copyableState(implode("\n", $order->addressLines())),
            TextEntry::make('contact')->label(__('billing.profile.fields.email'))->state($order->contact_email),
            TextEntry::make('note')->label(__('shop.checkout.note'))->state($order->customer_note)->placeholder('—'),
            TextEntry::make('carrier_text')->label(__('shop.tracking'))->state(trim($order->carrier.' '.$order->tracking_number))->placeholder('—'),
            TextEntry::make('reference')->label(__('admin.tenants.payments.provider_reference'))->state($payment?->provider_payment_id)->placeholder('—')->copyable(),
            TextEntry::make('cancel')->label(__('shop.admin.orders.cancel_reason'))->state($order->cancel_reason)->placeholder('—'),
        ];
    }

    /**
     * ZIP with one SVG and PNG per ordered code; names start with the line
     * number, the product and the number of copies.
     */
    private static function qrArchive(ShopOrder $order): StreamedResponse
    {
        $images = app(QrCodeImage::class);
        $path = tempnam(sys_get_temp_dir(), 'shop-order-');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The QR archive could not be created.');
        }

        try {
            foreach (app(ShopOrderAdminService::class)->items($order)->whereNotNull('qr_code')->values() as $index => $item) {
                // The snapshot, not the live row: the code is printed as ordered. Never saved.
                $code = (new QrCode)->forceFill(['code' => $item->qr_code, 'label' => $item->qr_label]);
                $prefix = sprintf('%02d-%s-x%d-', $index + 1, Str::limit(Str::slug($item->product_name), 40, ''), $item->quantity);
                $zip->addFromString($prefix.$images->filename($code, 'svg'), $images->svg($code));
                $zip->addFromString($prefix.$images->filename($code, 'png'), $images->png($code));
            }

            // An archive without entries is not written at all.
            if ($zip->count() === 0) {
                $zip->addFromString('README.txt', __('shop.admin.orders.no_codes'));
            }

            $zip->close();
            $contents = (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, "{$order->order_number}-qr.zip", ['Content-Type' => 'application/zip']);
    }
}
