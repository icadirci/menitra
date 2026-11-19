<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Locations;

use App\Enums\MediaType;
use App\Enums\MenuTheme;
use App\Filament\App\Forms\TenantImageUpload;
use App\Filament\App\Forms\TranslatableFields;
use App\Filament\App\Navigation\AppNavigationGroup;
use App\Filament\App\Resources\Locations\Pages\CreateLocation;
use App\Filament\App\Resources\Locations\Pages\EditLocation;
use App\Filament\App\Resources\Locations\Pages\ListLocations;
use App\Filament\App\Resources\Locations\Pages\ViewLocation;
use App\Filament\App\Resources\Locations\RelationManagers\SpecialHoursRelationManager;
use App\Filament\App\Resources\Locations\RelationManagers\TablesRelationManager;
use App\Filament\App\Support\PanelContext;
use App\Filament\App\Support\ServiceCall;
use App\Models\Location;
use App\Models\TenantMembership;
use App\Services\Locations\LocationService;
use App\Services\Themes\ThemeService;
use App\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Branches of the business. Authorization: LocationPolicy (owners: all
 * branches, create/delete/default; managers: update assigned branches;
 * branch staff: view assigned branches). The query itself is limited to the
 * member's accessible branches, so another branch's URL is a 404.
 * Writes go through LocationService / LocationHoursService.
 */
class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string|UnitEnum|null $navigationGroup = AppNavigationGroup::Locations;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'locations';

    /** Fields of location_translations edited here. */
    public const array TRANSLATED = ['name', 'description'];

    public static function getModelLabel(): string
    {
        return __('panels.app.locations.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panels.app.locations.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('panels.app.locations.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('panels.app.locations.sections.general'))
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TranslatableFields::make(fn (string $locale, bool $isDefault): array => [
                        TextInput::make('name')
                            ->label(__('panels.app.locations.fields.name'))
                            ->required($isDefault)
                            ->maxLength(120),
                        Textarea::make('description')
                            ->label(__('panels.app.locations.fields.description'))
                            ->rows(3)
                            ->maxLength(1000),
                    ])->columnSpanFull(),
                    TextInput::make('slug')
                        ->label(__('panels.app.locations.fields.slug'))
                        ->helperText(__('panels.app.locations.fields.slug_help'))
                        ->required()
                        ->minLength(2)
                        ->maxLength(64)
                        ->regex('/^[a-z0-9](-?[a-z0-9])*$/')
                        ->validationMessages(['regex' => __('panels.app.locations.errors.slug_format')])
                        // The unique index includes soft-deleted branches.
                        ->unique(
                            table: 'locations',
                            column: 'slug',
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('tenant_id', app(TenantContext::class)->id()),
                        ),
                    Toggle::make('is_active')
                        ->label(__('panels.app.locations.fields.is_active'))
                        ->helperText(__('panels.app.locations.fields.is_active_help'))
                        ->default(true)
                        ->inline(false),
                ]),
            Section::make(__('panels.app.locations.sections.contact'))
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextInput::make('phone')
                        ->label(__('panels.app.locations.fields.phone'))
                        ->tel()
                        ->regex('/^\+?[0-9 ()\-]{7,20}$/')
                        ->maxLength(32),
                    TextInput::make('whatsapp')
                        ->label(__('panels.app.locations.fields.whatsapp'))
                        ->tel()
                        ->regex('/^\+?[0-9 ()\-]{7,20}$/')
                        ->maxLength(32),
                    TextInput::make('email')
                        ->label(__('panels.app.locations.fields.email'))
                        ->email()
                        ->rule('email:rfc')
                        ->maxLength(255),
                ]),
            Section::make(__('panels.app.locations.sections.address'))
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextInput::make('address_line')
                        ->label(__('panels.app.locations.fields.address_line'))
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('district')
                        ->label(__('panels.app.locations.fields.district'))
                        ->maxLength(255),
                    TextInput::make('city')
                        ->label(__('panels.app.locations.fields.city'))
                        ->maxLength(255),
                    TextInput::make('postal_code')
                        ->label(__('panels.app.locations.fields.postal_code'))
                        ->maxLength(16),
                    TextInput::make('country_code')
                        ->label(__('panels.app.locations.fields.country_code'))
                        ->default('TR')
                        ->required()
                        ->length(2)
                        ->regex('/^[A-Z]{2}$/'),
                    TextInput::make('latitude')
                        ->label(__('panels.app.locations.fields.latitude'))
                        ->numeric()
                        ->minValue(-90)
                        ->maxValue(90),
                    TextInput::make('longitude')
                        ->label(__('panels.app.locations.fields.longitude'))
                        ->numeric()
                        ->minValue(-180)
                        ->maxValue(180),
                    Select::make('timezone')
                        ->label(__('panels.app.locations.fields.timezone'))
                        ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                        ->searchable()
                        ->default('Europe/Istanbul')
                        ->required(),
                ]),
            Section::make(__('panels.app.locations.sections.cover'))
                ->columnSpanFull()
                ->schema([
                    TenantImageUpload::make('cover_path', MediaType::Location)
                        ->label(__('panels.app.locations.fields.cover'))
                        ->helperText(__('panels.app.locations.fields.cover_help')),
                ]),
            Section::make(__('panels.app.locations.sections.appearance'))
                ->columnSpanFull()
                ->schema([self::themeField()]),
            Section::make(__('panels.app.hours.weekly'))
                ->description(__('panels.app.hours.weekly_help'))
                ->columnSpanFull()
                ->schema([
                    Repeater::make('opening_hours')
                        ->hiddenLabel()
                        ->columns(3)
                        ->defaultItems(0)
                        ->maxItems(21)
                        ->reorderable(false)
                        ->addActionLabel(__('panels.app.hours.add'))
                        ->schema([
                            Select::make('day_of_week')
                                ->label(__('panels.app.hours.fields.day'))
                                ->options(self::dayOptions())
                                ->required(),
                            TimePicker::make('opens_at')
                                ->label(__('panels.app.hours.fields.opens_at'))
                                ->seconds(false)
                                ->required(),
                            TimePicker::make('closes_at')
                                ->label(__('panels.app.hours.fields.closes_at'))
                                ->helperText(__('panels.app.hours.fields.closes_at_help'))
                                ->seconds(false)
                                ->required()
                                ->different('opens_at'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('panels.app.locations.fields.name'))
                    ->state(fn (Location $record): string => $record->translate()?->name ?? $record->slug)
                    ->description(fn (Location $record): string => $record->slug),
                TextColumn::make('city')
                    ->label(__('panels.app.locations.fields.city'))
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label(__('panels.app.locations.fields.is_active'))
                    ->boolean(),
                TextColumn::make('is_default')
                    ->label('')
                    ->state(fn (Location $record): ?string => $record->is_default ? __('panels.app.locations.default') : null)
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                TrashedFilter::make()
                    ->visible(fn (): bool => self::membership()?->isOwner() ?? false),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::makeDefaultAction(),
                self::deleteAction(),
                self::restoreAction(),
            ]);
    }

    /**
     * Theme override (owner-only, LocationPolicy::setTheme). Other roles see
     * it read-only and it is never dehydrated for them; the pages re-check
     * the policy and ThemeService enforces built themes and premium_themes.
     */
    public static function themeField(): Select
    {
        $canSet = fn (?Location $record): bool => $record === null
            ? (self::membership()?->isOwner() ?? false)
            : (auth()->user()?->can('setTheme', $record) ?? false);

        return Select::make('theme')
            ->label(__('panels.app.theme.location_field'))
            ->helperText(__('panels.app.theme.location_field_help'))
            ->placeholder(fn (): string => __('panels.app.theme.location_inherit', [
                'theme' => app(TenantContext::class)->tenant()->theme->label(),
            ]))
            ->options(collect(MenuTheme::cases())->mapWithKeys(fn (MenuTheme $theme): array => [
                $theme->value => $theme->isBuilt() ? $theme->label() : $theme->label().' · '.__('panels.app.theme.coming_soon'),
            ])->all())
            ->disableOptionWhen(fn (string $value, ?Location $record): bool => ! self::themeSelectable(MenuTheme::from($value), $record))
            ->disabled(fn (?Location $record): bool => ! $canSet($record))
            ->dehydrated(fn (?Location $record): bool => $canSet($record));
    }

    /**
     * Applies a submitted theme override (form key `theme`) through
     * ThemeService. Only owners' forms carry the key; a key from anyone else
     * is refused, not ignored.
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveTheme(Location $location, array $data): void
    {
        if (! array_key_exists('theme', $data)) {
            return;
        }

        $value = $data['theme'] instanceof MenuTheme ? $data['theme']->value : (string) ($data['theme'] ?? '');
        $theme = $value === '' ? null : MenuTheme::tryFrom($value);

        if ($theme === null && $value !== '') {
            throw ValidationException::withMessages(['theme' => __('panels.app.theme.errors.not_built')]);
        }

        if ($theme === $location->theme) {
            return;
        }

        abort_unless(auth()->user()?->can('setTheme', $location) ?? false, 403);

        app(ThemeService::class)->setLocationTheme($location, $theme);
    }

    private static function themeSelectable(MenuTheme $theme, ?Location $record): bool
    {
        $tenant = app(TenantContext::class)->tenant();

        return $theme->isBuilt()
            && ($theme === $record?->theme || app(ThemeService::class)->planAllows($tenant, $theme));
    }

    public static function makeDefaultAction(): Action
    {
        return Action::make('makeDefault')
            ->label(__('panels.app.locations.actions.make_default'))
            ->icon(Heroicon::OutlinedStar)
            ->authorize('setDefault')
            ->visible(fn (Location $record): bool => ! $record->is_default && $record->is_active && ! $record->trashed())
            ->requiresConfirmation()
            ->modalDescription(__('panels.app.locations.confirm.make_default'))
            ->successNotificationTitle(__('panels.app.locations.notifications.default_changed'))
            ->action(function (Location $record, Action $action): void {
                ServiceCall::run(fn () => app(LocationService::class)->makeDefault($record), statePath: null);
                $action->success();
            });
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->hidden(fn (Location $record): bool => $record->is_default || $record->trashed())
            ->modalDescription(__('panels.app.locations.confirm.delete'))
            ->using(function (Location $record): bool {
                ServiceCall::run(fn () => app(LocationService::class)->delete($record), statePath: null);

                return true;
            });
    }

    public static function restoreAction(): RestoreAction
    {
        return RestoreAction::make()
            ->using(function (Location $record): bool {
                ServiceCall::run(fn () => app(LocationService::class)->restore($record), statePath: null);

                return true;
            });
    }

    public static function getRelations(): array
    {
        return [
            SpecialHoursRelationManager::class,
            TablesRelationManager::class,
        ];
    }

    /**
     * Only branches the member may act on: all for owners, assigned ones for
     * managers and branch staff (LocationPolicy re-checks every record).
     */
    public static function getEloquentQuery(): Builder
    {
        $membership = self::membership();

        return parent::getEloquentQuery()
            ->with('translations')
            ->when(
                $membership === null,
                fn (Builder $query) => $query->whereRaw('1 = 0'),
                fn (Builder $query) => $query->when(
                    ! $membership->role->reachesAllLocations(),
                    fn (Builder $query) => $query->whereIn('id', $membership->accessibleLocations()->select('id')),
                ),
            );
    }

    /**
     * Soft-deleted branches stay reachable for restoring (owners only).
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Location ? ($record->translate()?->name ?? $record->slug) : '';
    }

    /**
     * Form state of the weekly hours.
     *
     * @return list<array{day_of_week: int, opens_at: string, closes_at: string}>
     */
    public static function openingHoursState(Location $location): array
    {
        return $location->openingHours()->get()
            ->map(fn ($row): array => [
                'day_of_week' => $row->day_of_week,
                'opens_at' => substr((string) $row->opens_at, 0, 5),
                'closes_at' => substr((string) $row->closes_at, 0, 5),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string> ISO weekday => localised name
     */
    public static function dayOptions(): array
    {
        $days = [];

        foreach (range(1, 7) as $day) {
            $days[$day] = __("panels.app.hours.days.{$day}");
        }

        return $days;
    }

    public static function membership(): ?TenantMembership
    {
        return PanelContext::membership();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocations::route('/'),
            'create' => CreateLocation::route('/create'),
            'view' => ViewLocation::route('/{record}'),
            'edit' => EditLocation::route('/{record}/edit'),
        ];
    }
}
