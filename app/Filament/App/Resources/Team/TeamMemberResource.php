<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Team;

use App\Enums\TenantRole;
use App\Filament\App\Navigation\AppNavigationGroup;
use App\Filament\App\Resources\Team\Pages\ListTeamMembers;
use App\Filament\App\Support\PanelContext;
use App\Filament\App\Support\ServiceCall;
use App\Models\Location;
use App\Models\TenantMembership;
use App\Services\Team\TeamService;
use App\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Team members of the current tenant (launch plan Faz 7). Owner-only
 * (TenantMembershipPolicy, also through canAccess()); every action is
 * authorized again and goes through TeamService. The owner's own row is
 * read-only. Memberships aren't tenant-scoped, so the query is constrained
 * to the tenant in TenantContext explicitly (Filament's tenant scoping is
 * off for this model).
 */
class TeamMemberResource extends Resource
{
    protected static ?string $model = TenantMembership::class;

    protected static string|UnitEnum|null $navigationGroup = AppNavigationGroup::Business;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'team';

    /**
     * Filament's tenancy would add a global scope (and a creating hook) to
     * TenantMembership itself, which breaks membership lookups for other
     * tenants (canAccessTenant, tenant switcher). The query below constrains
     * the tenant explicitly instead.
     */
    protected static bool $isScopedToTenant = false;

    public static function getModelLabel(): string
    {
        return __('team.members.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('team.members.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('team.members.navigation');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('team.fields.name'))
                    ->description(fn (TenantMembership $record): string => (string) $record->user?->email)
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('team.fields.role'))
                    ->badge()
                    ->formatStateUsing(fn (TenantRole $state): string => $state->label())
                    ->color(fn (TenantRole $state): string => $state === TenantRole::Owner ? 'primary' : 'gray'),
                TextColumn::make('branches')
                    ->label(__('team.fields.locations'))
                    ->state(fn (TenantMembership $record): string => self::branchSummary($record))
                    ->wrap(),
                IconColumn::make('is_active')
                    ->label(__('team.fields.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('team.fields.joined'))
                    ->date(),
            ])
            ->recordActions([
                self::editAction(),
                self::toggleActiveAction(),
                self::removeAction(),
            ]);
    }

    public static function editAction(): Action
    {
        return Action::make('editMember')
            ->label(__('team.actions.edit'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->authorize('update')
            ->modalHeading(__('team.actions.edit'))
            ->fillForm(fn (TenantMembership $record): array => [
                'role' => $record->role->value,
                'location_ids' => $record->assignedLocationIds()->pluck('location_id')->map(fn ($id): string => (string) $id)->all(),
            ])
            ->schema([
                self::roleField(),
                self::locationsField(),
            ])
            ->successNotificationTitle(__('team.notifications.saved'))
            ->action(function (TenantMembership $record, array $data, Action $action): void {
                ServiceCall::run(fn () => app(TeamService::class)->update(
                    self::owner(),
                    $record,
                    $data['role'] ?? null,
                    array_values((array) ($data['location_ids'] ?? [])),
                ), statePath: null);
                $action->success();
            });
    }

    public static function toggleActiveAction(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (TenantMembership $record): string => __($record->is_active ? 'team.actions.deactivate' : 'team.actions.activate'))
            ->icon(fn (TenantMembership $record): Heroicon => $record->is_active ? Heroicon::OutlinedPauseCircle : Heroicon::OutlinedPlayCircle)
            ->color(fn (TenantMembership $record): string => $record->is_active ? 'warning' : 'success')
            ->authorize('update')
            ->requiresConfirmation()
            ->modalDescription(fn (TenantMembership $record): ?string => $record->is_active ? __('team.confirm.deactivate') : null)
            ->successNotificationTitle(__('team.notifications.saved'))
            ->action(function (TenantMembership $record, Action $action): void {
                ServiceCall::run(function () use ($record): void {
                    $service = app(TeamService::class);
                    $record->is_active ? $service->deactivate(self::owner(), $record) : $service->activate(self::owner(), $record);
                }, statePath: null);
                $action->success();
            });
    }

    public static function removeAction(): Action
    {
        return Action::make('remove')
            ->label(__('team.actions.remove'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->authorize('delete')
            ->requiresConfirmation()
            ->modalDescription(__('team.confirm.remove'))
            ->successNotificationTitle(__('team.notifications.removed'))
            ->action(function (TenantMembership $record, Action $action): void {
                ServiceCall::run(fn () => app(TeamService::class)->remove(self::owner(), $record), statePath: null);
                $action->success();
            });
    }

    public static function roleField(): Select
    {
        return Select::make('role')
            ->label(__('team.fields.role'))
            ->helperText(__('team.fields.role_help'))
            ->options([
                TenantRole::Manager->value => TenantRole::Manager->label(),
                TenantRole::BranchStaff->value => TenantRole::BranchStaff->label(),
            ])
            ->required()
            ->selectablePlaceholder(false);
    }

    public static function locationsField(): CheckboxList
    {
        return CheckboxList::make('location_ids')
            ->label(__('team.fields.locations'))
            ->helperText(__('team.fields.locations_help'))
            ->options(fn (): array => self::locationOptions())
            ->required()
            ->minItems(1);
    }

    /**
     * The tenant's branches (tenant scope; deleted branches excluded).
     *
     * @return array<int, string>
     */
    public static function locationOptions(): array
    {
        return Location::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (Location $location): array => [$location->getKey() => $location->translate()?->name ?? $location->slug])
            ->all();
    }

    public static function branchSummary(TenantMembership $record): string
    {
        if ($record->role->reachesAllLocations()) {
            return __('team.all_locations');
        }

        $names = $record->locations->map(fn (Location $location): string => $location->translate()?->name ?? $location->slug);

        return $names->isEmpty() ? __('team.no_locations') : $names->implode(', ');
    }

    /**
     * The acting owner's membership (TeamService re-checks the role).
     */
    public static function owner(): TenantMembership
    {
        return PanelContext::membership() ?? abort(403);
    }

    public static function getEloquentQuery(): Builder
    {
        $context = app(TenantContext::class);

        return parent::getEloquentQuery()
            ->with(['user', 'locations.translations'])
            ->when(
                $context->check(),
                fn (Builder $query) => $query->where('tenant_id', $context->id()),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamMembers::route('/'),
        ];
    }
}
