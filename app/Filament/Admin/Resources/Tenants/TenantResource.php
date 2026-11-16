<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Tenants;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Filament\Admin\Navigation\AdminNavigationGroup;
use App\Filament\Admin\Resources\Tenants\Pages\ListTenants;
use App\Filament\Admin\Resources\Tenants\Pages\ViewTenant;
use App\Filament\Admin\Resources\Tenants\RelationManagers\LocationsRelationManager;
use App\Filament\Admin\Resources\Tenants\RelationManagers\MembersRelationManager;
use App\Filament\Admin\Resources\Tenants\RelationManagers\SubscriptionEventsRelationManager;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Admin\TenantInspector;
use App\Services\Menu\MenuUrlGenerator;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Tenant management in /admin (launch plan Faz 8). Every platform role lists
 * and views tenants (TenantPolicy); state changes are super-admin actions on
 * the view page, through TenantAdminService (audited). Subscriptions are
 * read-only. Cross-tenant reads of scoped models go through TenantInspector
 * or plain subqueries here (documented admin bypass area, read-only).
 */
class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Platform;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'tenants';

    public static function getModelLabel(): string
    {
        return __('admin.tenants.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.tenants.plural');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations')->addSelect([
                // Live subscription and owner per listed tenant (read-only subqueries).
                'live_subscription_status' => self::liveSubscription('status'),
                'live_plan_id' => self::liveSubscription('plan_id'),
                'owner_email' => DB::table('tenant_user')
                    ->join('users', 'users.id', '=', 'tenant_user.user_id')
                    ->whereColumn('tenant_user.tenant_id', 'tenants.id')
                    ->where('tenant_user.role', TenantRole::Owner->value)
                    ->select('users.email')
                    ->limit(1),
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.tenants.fields.name'))
                    ->state(fn (Tenant $record): string => $record->getFilamentName())
                    ->description(fn (Tenant $record): string => $record->slug)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query) => $query
                        ->where('slug', 'ilike', '%'.$search.'%')
                        ->orWhereHas('translations', fn (Builder $translation) => $translation->where('name', 'ilike', '%'.$search.'%')))),
                TextColumn::make('owner_email')
                    ->label(__('admin.tenants.fields.owner'))
                    ->placeholder(__('admin.tenants.no_owner')),
                TextColumn::make('status')
                    ->label(__('admin.tenants.fields.status'))
                    ->badge()
                    ->state(fn (Tenant $record): string => $record->trashed() ? __('admin.tenants.deleted') : $record->status->label())
                    ->color(fn (Tenant $record): string => $record->trashed() ? 'gray' : $record->status->color()),
                TextColumn::make('live_plan_id')
                    ->label(__('admin.tenants.fields.plan'))
                    ->formatStateUsing(fn ($state): string => self::planName($state === null ? null : (int) $state))
                    ->placeholder('—'),
                TextColumn::make('live_subscription_status')
                    ->label(__('admin.tenants.fields.subscription'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => SubscriptionStatus::tryFrom((string) $state)?->label() ?? '—')
                    ->color(fn ($state): string => SubscriptionStatus::tryFrom((string) $state)?->color() ?? 'gray')
                    ->placeholder(__('admin.tenants.no_subscription')),
                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.tenants.fields.status'))
                    ->options(collect(TenantStatus::cases())->mapWithKeys(fn (TenantStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('subscription_status')
                    ->label(__('admin.tenants.fields.subscription'))
                    ->options(collect(SubscriptionStatus::cases())->mapWithKeys(fn (SubscriptionStatus $status): array => [$status->value => $status->label()])->all())
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null) ? $query : $query->whereIn(
                        'tenants.id',
                        DB::table('subscriptions')->select('tenant_id')->whereNull('ended_at')->where('status', $data['value']),
                    )),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.tenants.sections.general'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('name')
                        ->label(__('admin.tenants.fields.name'))
                        ->state(fn (Tenant $record): string => $record->getFilamentName()),
                    TextEntry::make('slug')
                        ->label(__('admin.tenants.fields.slug'))
                        ->copyable(),
                    TextEntry::make('menu_url')
                        ->label(__('admin.tenants.fields.menu_url'))
                        ->state(fn (Tenant $record): string => app(MenuUrlGenerator::class)->tenant($record->slug)),
                    TextEntry::make('status')
                        ->label(__('admin.tenants.fields.status'))
                        ->badge()
                        ->state(fn (Tenant $record): string => $record->trashed() ? __('admin.tenants.deleted') : $record->status->label())
                        ->color(fn (Tenant $record): string => $record->trashed() ? 'gray' : $record->status->color()),
                    TextEntry::make('default_locale')
                        ->label(__('admin.tenants.fields.default_locale')),
                    TextEntry::make('theme')
                        ->label(__('admin.tenants.fields.theme'))
                        ->formatStateUsing(fn ($state): string => $state instanceof BackedEnum ? (string) $state->value : (string) $state),
                    TextEntry::make('created_at')
                        ->label(__('admin.fields.created_at'))
                        ->dateTime(),
                    TextEntry::make('deleted_at')
                        ->label(__('admin.tenants.fields.deleted_at'))
                        ->dateTime()
                        ->visible(fn (Tenant $record): bool => $record->trashed()),
                ]),
            Section::make(__('admin.tenants.sections.subscription'))
                ->description(__('admin.tenants.sections.subscription_help'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('subscription_plan')
                        ->label(__('admin.tenants.fields.plan'))
                        ->state(fn (Tenant $record): ?string => self::subscription($record)?->plan?->translate()?->name ?? self::subscription($record)?->plan?->code)
                        ->placeholder(__('admin.tenants.no_subscription')),
                    TextEntry::make('subscription_status')
                        ->label(__('admin.tenants.fields.subscription'))
                        ->badge()
                        ->state(fn (Tenant $record): ?string => self::subscription($record)?->status->label())
                        ->color(fn (Tenant $record): string => self::subscription($record)?->status->color() ?? 'gray')
                        ->placeholder('—'),
                    TextEntry::make('subscription_access_until')
                        ->label(__('admin.tenants.fields.access_until'))
                        ->state(fn (Tenant $record) => self::subscription($record)?->accessUntil())
                        ->dateTime()
                        ->placeholder('—'),
                    TextEntry::make('subscription_trial_ends_at')
                        ->label(__('admin.tenants.fields.trial_ends_at'))
                        ->state(fn (Tenant $record) => self::subscription($record)?->trial_ends_at)
                        ->dateTime()
                        ->placeholder('—'),
                    TextEntry::make('subscription_period_ends_at')
                        ->label(__('admin.tenants.fields.period_ends_at'))
                        ->state(fn (Tenant $record) => self::subscription($record)?->current_period_ends_at)
                        ->dateTime()
                        ->placeholder('—'),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
            LocationsRelationManager::class,
            SubscriptionEventsRelationManager::class,
        ];
    }

    /**
     * Deleted tenants stay reachable on their view page (restore).
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'view' => ViewTenant::route('/{record}'),
        ];
    }

    private static function liveSubscription(string $column): QueryBuilder
    {
        return DB::table('subscriptions')
            ->select($column)
            ->whereColumn('subscriptions.tenant_id', 'tenants.id')
            ->whereNull('ended_at')
            ->orderByDesc('id')
            ->limit(1);
    }

    private static function planName(?int $planId): string
    {
        if ($planId === null) {
            return '—';
        }

        // Plans are few: loaded once per request for the whole table.
        $names = once(fn (): array => Plan::query()->with('translations')->get()
            ->mapWithKeys(fn (Plan $plan): array => [$plan->getKey() => $plan->translate()?->name ?? $plan->code])
            ->all());

        return $names[$planId] ?? '—';
    }

    private static function subscription(Tenant $tenant): ?Subscription
    {
        if (! array_key_exists('admin_live_subscription', $tenant->getRelations())) {
            $tenant->setRelation('admin_live_subscription', app(TenantInspector::class)->liveSubscription($tenant));
        }

        return $tenant->getRelation('admin_live_subscription');
    }
}
