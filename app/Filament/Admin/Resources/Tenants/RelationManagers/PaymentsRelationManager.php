<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Tenants\RelationManagers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Admin\TenantInspector;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * A tenant's payments (read-only; refunds are made in the iyzico merchant
 * panel). Billing details (tax id, address) are not shown here. Documented
 * admin scope bypass through TenantInspector.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.tenants.payments.title');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRelationship(): Relation|Builder
    {
        /** @var Tenant $tenant */
        $tenant = $this->getOwnerRecord();

        return app(TenantInspector::class)->payments($tenant);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.tenants.payments.created_at'))
                    ->dateTime(),
                TextColumn::make('status')
                    ->label(__('admin.tenants.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color()),
                TextColumn::make('amount')
                    ->label(__('admin.tenants.payments.amount'))
                    ->formatStateUsing(fn (Payment $record): string => $record->money()->format()),
                TextColumn::make('interval')
                    ->label(__('admin.tenants.payments.interval'))
                    ->formatStateUsing(fn (Payment $record): string => $record->periodLabel()),
                TextColumn::make('receipt_number')
                    ->label(__('admin.tenants.payments.receipt'))
                    ->placeholder('—'),
                TextColumn::make('provider_payment_id')
                    ->label(__('admin.tenants.payments.provider_reference'))
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('failure_code')
                    ->label(__('admin.tenants.payments.failure'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.tenants.fields.status'))
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $status): array => [$status->value => $status->label()])->all()),
            ]);
    }
}
