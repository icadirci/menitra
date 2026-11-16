<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Enums\TenantRole;
use App\Filament\Admin\Resources\Tenants\TenantResource;
use App\Models\TenantMembership;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Business memberships of a user (read-only), deleted tenants included.
 */
class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.users.memberships');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['tenant' => fn ($tenant) => $tenant->withTrashed()->with('translations')]))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('tenant_name')
                    ->label(__('admin.tenants.singular'))
                    ->state(fn (TenantMembership $record): string => $record->tenant?->getFilamentName() ?? '—')
                    ->description(fn (TenantMembership $record): string => (string) $record->tenant?->slug)
                    ->url(fn (TenantMembership $record): string => TenantResource::getUrl('view', ['record' => $record->tenant_id])),
                TextColumn::make('role')
                    ->label(__('admin.users.fields.role'))
                    ->badge()
                    ->formatStateUsing(fn (TenantRole $state): string => $state->label()),
                IconColumn::make('is_active')
                    ->label(__('admin.fields.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('admin.users.fields.joined'))
                    ->date(),
            ]);
    }
}
