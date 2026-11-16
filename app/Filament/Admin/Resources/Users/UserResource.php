<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users;

use App\Enums\PlatformRole;
use App\Filament\Admin\Navigation\AdminNavigationGroup;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\Pages\ViewUser;
use App\Filament\Admin\Resources\Users\RelationManagers\MembershipsRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * User management in /admin (launch plan Faz 8). Every platform role lists
 * and views users (UserPolicy) and may resend verification mails; creating
 * platform users, changing platform roles, deactivating and impersonating
 * are super-admin actions through UserAdminService / Impersonation (audited).
 * No tenant-scoped data is read here (memberships and tenants aren't scoped).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Platform;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'users';

    public static function getModelLabel(): string
    {
        return __('admin.users.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.users.plural');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['memberships' => fn (Builder $membership) => $membership->where('is_active', true)]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.users.fields.name'))
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(['name', 'email']),
                TextColumn::make('platform_role')
                    ->label(__('admin.users.fields.platform_role'))
                    ->badge()
                    ->formatStateUsing(fn (PlatformRole $state): string => $state->label())
                    ->color(fn (PlatformRole $state): string => $state === PlatformRole::SuperAdmin ? 'danger' : 'warning')
                    ->placeholder('—'),
                TextColumn::make('memberships_count')
                    ->label(__('admin.users.fields.memberships')),
                IconColumn::make('email_verified_at')
                    ->label(__('admin.users.fields.verified'))
                    ->state(fn (User $record): bool => $record->hasVerifiedEmail())
                    ->boolean(),
                IconColumn::make('two_factor')
                    ->label(__('admin.users.fields.two_factor'))
                    ->state(fn (User $record): bool => $record->hasTwoFactorEnabled())
                    ->boolean(),
                TextColumn::make('deactivated_at')
                    ->label(__('admin.users.fields.status'))
                    ->badge()
                    ->state(fn (User $record): string => $record->isDeactivated() ? __('admin.users.deactivated') : __('admin.users.active'))
                    ->color(fn (User $record): string => $record->isDeactivated() ? 'danger' : 'success'),
                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('platform_role')
                    ->label(__('admin.users.fields.platform_role'))
                    ->options(collect(PlatformRole::cases())->mapWithKeys(fn (PlatformRole $role): array => [$role->value => $role->label()])->all()),
                TernaryFilter::make('email_verified_at')
                    ->label(__('admin.users.fields.verified'))
                    ->nullable(),
                TernaryFilter::make('deactivated_at')
                    ->label(__('admin.users.deactivated'))
                    ->nullable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.users.sections.account'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('name')
                        ->label(__('admin.users.fields.name')),
                    TextEntry::make('email')
                        ->label(__('admin.users.fields.email'))
                        ->copyable(),
                    TextEntry::make('platform_role')
                        ->label(__('admin.users.fields.platform_role'))
                        ->badge()
                        ->formatStateUsing(fn (PlatformRole $state): string => $state->label())
                        ->placeholder('—'),
                    TextEntry::make('email_verified_at')
                        ->label(__('admin.users.fields.verified_at'))
                        ->dateTime()
                        ->placeholder(__('admin.users.not_verified')),
                    IconEntry::make('two_factor')
                        ->label(__('admin.users.fields.two_factor'))
                        ->state(fn (User $record): bool => $record->hasTwoFactorEnabled())
                        ->boolean(),
                    TextEntry::make('deactivated_at')
                        ->label(__('admin.users.fields.deactivated_at'))
                        ->dateTime()
                        ->placeholder('—'),
                    TextEntry::make('created_at')
                        ->label(__('admin.fields.created_at'))
                        ->dateTime(),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            MembershipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
