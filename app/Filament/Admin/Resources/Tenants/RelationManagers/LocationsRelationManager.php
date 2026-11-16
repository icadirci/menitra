<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Tenants\RelationManagers;

use App\Models\Location;
use App\Models\Tenant;
use App\Services\Admin\TenantInspector;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Branches of a tenant (read-only, documented admin scope bypass through
 * TenantInspector: constrained to this tenant).
 */
class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locations';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.tenants.locations');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRelationship(): Relation|Builder
    {
        /** @var Tenant $tenant */
        $tenant = $this->getOwnerRecord();

        return app(TenantInspector::class)->locations($tenant);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations'))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.tenants.fields.name'))
                    ->state(fn (Location $record): string => $record->translate()?->name ?? $record->slug)
                    ->description(fn (Location $record): string => $record->slug),
                IconColumn::make('is_active')
                    ->label(__('admin.fields.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->date(),
            ]);
    }
}
