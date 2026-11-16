<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Tenants\RelationManagers;

use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Tenant;
use App\Services\Admin\TenantInspector;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Subscription history of a tenant (read-only; state changes go only through
 * SubscriptionService). Documented admin scope bypass through TenantInspector.
 */
class SubscriptionEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.tenants.subscription_events');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRelationship(): Relation|Builder
    {
        /** @var Tenant $tenant */
        $tenant = $this->getOwnerRecord();

        return app(TenantInspector::class)->subscriptionEvents($tenant);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label(__('admin.tenants.fields.occurred_at'))
                    ->dateTime(),
                TextColumn::make('type')
                    ->label(__('admin.tenants.fields.event'))
                    ->formatStateUsing(fn (SubscriptionEventType $state): string => __('admin.subscription_events.'.$state->value)),
                TextColumn::make('from_status')
                    ->label(__('admin.tenants.fields.from_status'))
                    ->formatStateUsing(fn (?SubscriptionStatus $state): string => $state?->label() ?? '—')
                    ->placeholder('—'),
                TextColumn::make('to_status')
                    ->label(__('admin.tenants.fields.to_status'))
                    ->badge()
                    ->formatStateUsing(fn (?SubscriptionStatus $state): string => $state?->label() ?? '—')
                    ->color(fn (?SubscriptionStatus $state): string => $state?->color() ?? 'gray')
                    ->placeholder('—'),
            ]);
    }
}
