<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Team;

use App\Enums\PlanFeature;
use App\Enums\TenantRole;
use App\Filament\App\Navigation\AppNavigationGroup;
use App\Filament\App\Resources\Team\Pages\ListTeamInvitations;
use App\Filament\App\Support\ServiceCall;
use App\Models\Location;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Services\Subscription\FeatureGate;
use App\Services\Team\TeamInvitationService;
use App\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Open team invitations of the current tenant (pending and expired; accepted
 * ones appear as members, revoked ones disappear). Owner-only
 * (TenantInvitationPolicy); sending, resending and revoking go through
 * TeamInvitationService, which enforces max_members.
 */
class TeamInvitationResource extends Resource
{
    protected static ?string $model = TenantInvitation::class;

    protected static string|UnitEnum|null $navigationGroup = AppNavigationGroup::Business;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 51;

    protected static ?string $slug = 'team-invitations';

    public static function getModelLabel(): string
    {
        return __('team.invitations.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('team.invitations.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('team.invitations.navigation');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('team.invitations.empty'))
            ->columns([
                TextColumn::make('email')
                    ->label(__('team.fields.email'))
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('team.fields.role'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (TenantRole $state): string => $state->label()),
                TextColumn::make('branches')
                    ->label(__('team.fields.locations'))
                    ->state(fn (TenantInvitation $record): string => $record->locations
                        ->map(fn (Location $location): string => $location->translate()?->name ?? $location->slug)
                        ->implode(', ') ?: __('team.no_locations'))
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('team.fields.status'))
                    ->badge()
                    ->state(fn (TenantInvitation $record): string => $record->status())
                    ->formatStateUsing(fn (string $state): string => __("team.invitations.statuses.{$state}"))
                    ->color(fn (string $state): string => $state === 'pending' ? 'success' : 'warning'),
                TextColumn::make('expires_at')
                    ->label(__('team.fields.expires_at'))
                    ->dateTime(),
            ])
            ->recordActions([
                self::resendAction(),
                self::revokeAction(),
            ]);
    }

    /**
     * Shown on both team pages. The disabled state is information only.
     */
    public static function inviteAction(): Action
    {
        return Action::make('invite')
            ->label(__('team.actions.invite'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->authorize(fn (): bool => auth()->user()?->can('create', TenantInvitation::class) ?? false)
            ->disabled(fn (): bool => ! self::canInviteMore())
            ->tooltip(fn (): ?string => self::canInviteMore() ? null : __('team.limit_reached'))
            ->modalHeading(__('team.actions.invite'))
            ->modalDescription(__('team.invite_help', ['days' => config('tenancy.team.invitation_days')]))
            ->modalSubmitActionLabel(__('team.actions.send'))
            ->schema([
                TextInput::make('email')
                    ->label(__('team.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(255),
                TeamMemberResource::roleField()->default(TenantRole::BranchStaff->value),
                TeamMemberResource::locationsField(),
            ])
            ->successNotificationTitle(__('team.notifications.invited'))
            ->action(function (array $data, Action $action): void {
                ServiceCall::run(fn () => app(TeamInvitationService::class)->invite(
                    TeamMemberResource::owner(),
                    (string) ($data['email'] ?? ''),
                    $data['role'] ?? null,
                    array_values((array) ($data['location_ids'] ?? [])),
                ), statePath: null);
                $action->success();
            });
    }

    public static function resendAction(): Action
    {
        return Action::make('resend')
            ->label(__('team.actions.resend'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->authorize('update')
            ->requiresConfirmation()
            ->modalDescription(__('team.confirm.resend'))
            ->successNotificationTitle(__('team.notifications.resent'))
            ->action(function (TenantInvitation $record, Action $action): void {
                ServiceCall::run(fn () => app(TeamInvitationService::class)->resend(TeamMemberResource::owner(), $record), statePath: null);
                $action->success();
            });
    }

    public static function revokeAction(): Action
    {
        return Action::make('revoke')
            ->label(__('team.actions.revoke'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('delete')
            ->requiresConfirmation()
            ->modalDescription(__('team.confirm.revoke'))
            ->successNotificationTitle(__('team.notifications.revoked'))
            ->action(function (TenantInvitation $record, Action $action): void {
                ServiceCall::run(fn () => app(TeamInvitationService::class)->revoke(TeamMemberResource::owner(), $record), statePath: null);
                $action->success();
            });
    }

    public static function canInviteMore(): bool
    {
        $context = app(TenantContext::class);

        return $context->check() && app(FeatureGate::class)->allows($context->tenant(), PlanFeature::MaxMembers);
    }

    /**
     * "Team members: 2 / 3" for owners (active members + pending invitations).
     */
    public static function usageText(): ?string
    {
        $context = app(TenantContext::class);
        $user = auth()->user();

        if (! $context->check() || ! $user instanceof User || ! $user->can('create', TenantInvitation::class)) {
            return null;
        }

        $gate = app(FeatureGate::class);
        $tenant = $context->tenant();
        $limit = $gate->limit($tenant, PlanFeature::MaxMembers);

        return __('team.usage', [
            'usage' => $limit === null
                ? __('plans.unlimited')
                : __('plans.usage', ['used' => $gate->usage($tenant, PlanFeature::MaxMembers), 'limit' => $limit]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->open()
            ->with('locations.translations');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamInvitations::route('/'),
        ];
    }
}
