<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Tenants\RelationManagers;

use App\Enums\TenantRole;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\TenantMembership;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Members of a tenant (read-only; memberships aren't tenant-scoped). Team
 * changes belong to the tenant's owner in /app.
 */
class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.tenants.members');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            // Branch count from the pivot: Location itself is tenant-scoped.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->addSelect([
                'locations_count' => DB::table('location_user')
                    ->selectRaw('count(*)')
                    ->whereColumn('location_user.tenant_user_id', 'tenant_user.id'),
            ]))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('admin.users.fields.name'))
                    ->description(fn (TenantMembership $record): string => (string) $record->user?->email)
                    ->url(fn (TenantMembership $record): string => UserResource::getUrl('view', ['record' => $record->user_id])),
                TextColumn::make('role')
                    ->label(__('admin.users.fields.role'))
                    ->badge()
                    ->formatStateUsing(fn (TenantRole $state): string => $state->label()),
                TextColumn::make('locations_count')
                    ->label(__('admin.users.fields.branches'))
                    ->formatStateUsing(fn ($state, TenantMembership $record): string => $record->role->reachesAllLocations() ? __('team.all_locations') : (string) $state),
                IconColumn::make('is_active')
                    ->label(__('admin.fields.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('admin.users.fields.joined'))
                    ->date(),
            ]);
    }
}
