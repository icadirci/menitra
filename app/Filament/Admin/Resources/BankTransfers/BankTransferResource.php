<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransfers;

use App\Enums\PaymentStatus;
use App\Filament\Admin\Navigation\AdminNavigationGroup;
use App\Filament\Admin\Resources\BankTransfers\Pages\ListBankTransfers;
use App\Filament\App\Support\ServiceCall;
use App\Models\Payment;
use App\Models\User;
use App\Services\Admin\PaymentAdminService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Bank transfers (havale/EFT) of every business: check the bank account for
 * the reference and amount, then approve (applies the payment like a card
 * payment) or reject. List page only; reads and decisions go through
 * PaymentAdminService (documented scope bypass, read-only; decisions in the
 * payment's own tenant context, audited). Super admins decide, platform
 * staff only read.
 */
class BankTransferResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $slug = 'bank-transfers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Subscriptions;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('billing.bank_transfer.admin.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('billing.bank_transfer.admin.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return app(PaymentAdminService::class)->bankTransfers();
    }

    /**
     * Transfers the owner reported as paid and nobody decided yet.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', PaymentStatus::Pending)->whereNotNull('transfer_notified_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('transfer_reference')
                    ->label(__('billing.bank_transfer.reference'))
                    ->copyable()
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('tenant_id')
                    ->label(__('admin.tenants.singular'))
                    ->formatStateUsing(fn (Payment $record): string => $record->tenant?->translate($record->tenant->default_locale)?->name ?? $record->tenant?->slug ?? '—'),
                TextColumn::make('kind')
                    ->label(__('billing.bank_transfer.admin.purpose'))
                    ->state(fn (Payment $record): string => self::purpose($record)),
                TextColumn::make('amount')
                    ->label(__('billing.bank_transfer.amount'))
                    ->formatStateUsing(fn (Payment $record): string => $record->money()->format()),
                TextColumn::make('status')
                    ->label(__('admin.tenants.fields.status'))
                    ->badge()
                    ->state(fn (Payment $record): string => self::statusLabel($record))
                    ->color(fn (Payment $record): string => match (true) {
                        $record->isPending() && $record->transfer_notified_at !== null => 'warning',
                        default => $record->status->color(),
                    })
                    ->description(fn (Payment $record): ?string => $record->failure_message),
                TextColumn::make('transfer_notified_at')
                    ->label(__('billing.bank_transfer.admin.notified_at'))
                    ->dateTime(timezone: 'Europe/Istanbul')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('admin.tenants.payments.created_at'))
                    ->dateTime(timezone: 'Europe/Istanbul')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.tenants.fields.status'))
                    ->default(PaymentStatus::Pending->value)
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('billing.bank_transfer.admin.approve'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->authorize('review')
                    ->visible(fn (Payment $record): bool => $record->isOpenBankTransfer())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Payment $record): string => __('billing.bank_transfer.admin.approve_heading', ['reference' => $record->transfer_reference]))
                    ->modalDescription(fn (Payment $record): string => __('billing.bank_transfer.admin.approve_confirm', ['amount' => $record->money()->format()]))
                    ->successNotificationTitle(__('billing.bank_transfer.admin.approved'))
                    ->action(function (Payment $record, Action $action): void {
                        ServiceCall::run(fn () => app(PaymentAdminService::class)->approve(self::actor(), $record), statePath: null);
                        $action->success();
                    }),
                Action::make('reject')
                    ->label(__('billing.bank_transfer.admin.reject'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->authorize('review')
                    ->visible(fn (Payment $record): bool => $record->isOpenBankTransfer())
                    ->modalDescription(__('billing.bank_transfer.admin.reject_confirm'))
                    ->schema([
                        Textarea::make('reason')
                            ->label(__('billing.bank_transfer.admin.reject_reason'))
                            ->helperText(__('billing.bank_transfer.admin.reject_reason_help'))
                            ->required()
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->successNotificationTitle(__('billing.bank_transfer.admin.rejected'))
                    ->action(function (Payment $record, array $data, Action $action): void {
                        ServiceCall::run(fn () => app(PaymentAdminService::class)->reject(self::actor(), $record, (string) $data['reason']), statePath: null);
                        $action->success();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankTransfers::route('/'),
        ];
    }

    private static function purpose(Payment $record): string
    {
        if ($record->isShopOrder()) {
            return __('billing.bank_transfer.admin.shop_order', ['number' => $record->getAttribute('shop_order_number') ?? '—']);
        }

        $plan = $record->plan?->translate()?->name ?? $record->plan?->code ?? '—';

        return $record->isUpgrade()
            ? __('billing.bank_transfer.admin.upgrade', ['plan' => $plan])
            : $plan.' · '.$record->periodLabel();
    }

    private static function statusLabel(Payment $record): string
    {
        return match (true) {
            $record->isPending() && $record->transfer_notified_at !== null => __('billing.bank_transfer.statuses.notified'),
            $record->isPending() => __('billing.bank_transfer.statuses.awaiting'),
            $record->failure_code === 'cancelled' => __('billing.bank_transfer.statuses.cancelled'),
            $record->failure_code === 'transfer_rejected' => __('billing.bank_transfer.statuses.rejected'),
            default => $record->status->label(),
        };
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
