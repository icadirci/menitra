<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AuditEvents;

use App\Enums\AdminAuditAction;
use App\Filament\Admin\Navigation\AdminNavigationGroup;
use App\Filament\Admin\Resources\AuditEvents\Pages\ListAdminAuditEvents;
use App\Filament\Admin\Resources\Tenants\TenantResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\AdminAuditEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The append-only admin audit log, read-only for every platform role
 * (AdminAuditEventPolicy).
 */
class AdminAuditEventResource extends Resource
{
    protected static ?string $model = AdminAuditEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Platform;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'audit-log';

    public static function getModelLabel(): string
    {
        return __('admin.audit.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.audit.plural');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['actor', 'tenant']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.audit.fields.created_at'))
                    ->dateTime(),
                TextColumn::make('actor.email')
                    ->label(__('admin.audit.fields.actor'))
                    ->placeholder('—'),
                TextColumn::make('action')
                    ->label(__('admin.audit.fields.action'))
                    ->badge()
                    ->formatStateUsing(fn (AdminAuditAction $state): string => $state->label()),
                TextColumn::make('subject')
                    ->label(__('admin.audit.fields.subject'))
                    ->state(fn (AdminAuditEvent $record): ?string => $record->subject_type === null ? null : __('admin.audit.subjects.'.$record->subject_type).' #'.$record->subject_id)
                    ->url(fn (AdminAuditEvent $record): ?string => match ($record->subject_type) {
                        'tenant' => TenantResource::getUrl('view', ['record' => $record->subject_id]),
                        'user' => UserResource::getUrl('view', ['record' => $record->subject_id]),
                        default => null,
                    })
                    ->placeholder('—'),
                TextColumn::make('tenant.slug')
                    ->label(__('admin.audit.fields.tenant'))
                    ->placeholder('—'),
                TextColumn::make('meta')
                    ->label(__('admin.audit.fields.meta'))
                    ->state(fn (AdminAuditEvent $record): ?string => $record->meta === null ? null : collect($record->meta)
                        ->map(fn ($value, string $key): string => $key.': '.(is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value)))
                        ->implode(', '))
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('ip')
                    ->label(__('admin.audit.fields.ip'))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('admin.audit.fields.action'))
                    ->options(collect(AdminAuditAction::cases())->mapWithKeys(fn (AdminAuditAction $action): array => [$action->value => $action->label()])->all()),
                SelectFilter::make('subject_type')
                    ->label(__('admin.audit.fields.subject'))
                    ->options(['tenant' => __('admin.audit.subjects.tenant'), 'user' => __('admin.audit.subjects.user')]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminAuditEvents::route('/'),
        ];
    }
}
