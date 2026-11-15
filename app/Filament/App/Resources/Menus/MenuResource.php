<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menus;

use App\Enums\MenuType;
use App\Filament\App\Forms\TranslatableFields;
use App\Filament\App\Navigation\AppNavigationGroup;
use App\Filament\App\Resources\Menus\Pages\CreateMenu;
use App\Filament\App\Resources\Menus\Pages\EditMenu;
use App\Filament\App\Resources\Menus\Pages\ListMenus;
use App\Filament\App\Resources\Menus\RelationManagers\CategoriesRelationManager;
use App\Filament\App\Resources\Menus\RelationManagers\LocationsRelationManager;
use App\Filament\App\Support\PanelContext;
use App\Filament\App\Support\ServiceCall;
use App\Models\Menu;
use App\Services\Menus\MenuService;
use App\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use UnitEnum;

/**
 * Menus of the business. Authorization: MenuPolicy (owners: all; managers:
 * menus fully inside their branches). The list query applies the same rule,
 * so other menus are a 404. Writes go through MenuService.
 */
class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static string|UnitEnum|null $navigationGroup = AppNavigationGroup::MenuManagement;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'menus';

    public const array TRANSLATED = ['name', 'description'];

    public static function getModelLabel(): string
    {
        return __('panels.app.menus.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panels.app.menus.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('panels.app.menus.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TranslatableFields::make(fn (string $locale, bool $isDefault): array => [
                        TextInput::make('name')
                            ->label(__('panels.app.menus.fields.name'))
                            ->required($isDefault)
                            ->maxLength(120),
                        Textarea::make('description')
                            ->label(__('panels.app.menus.fields.description'))
                            ->rows(2)
                            ->maxLength(1000),
                    ])->columnSpanFull(),
                    TextInput::make('slug')
                        ->label(__('panels.app.menus.fields.slug'))
                        ->helperText(__('panels.app.menus.fields.slug_help'))
                        ->required()
                        ->minLength(2)
                        ->maxLength(64)
                        ->regex('/^[a-z0-9](-?[a-z0-9])*$/')
                        ->validationMessages(['regex' => __('panels.app.locations.errors.slug_format')])
                        ->unique(
                            table: 'menus',
                            column: 'slug',
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('tenant_id', app(TenantContext::class)->id()),
                        ),
                    Select::make('type')
                        ->label(__('panels.app.menus.fields.type'))
                        ->options(MenuType::class)
                        ->default(MenuType::AllDay)
                        ->required(),
                    Toggle::make('is_published')
                        ->label(__('panels.app.menus.fields.is_published'))
                        ->helperText(__('panels.app.menus.fields.is_published_help'))
                        ->default(false)
                        ->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('panels.app.menus.fields.name'))
                    ->state(fn (Menu $record): string => $record->translate()?->name ?? $record->slug)
                    ->description(fn (Menu $record): string => $record->slug),
                TextColumn::make('type')
                    ->label(__('panels.app.menus.fields.type'))
                    ->badge(),
                TextColumn::make('locations')
                    ->label(__('panels.app.menus.fields.locations'))
                    ->state(fn (Menu $record): array => $record->locations->map(fn ($location) => $location->translate()?->name ?? $location->slug)->all())
                    ->badge()
                    ->color('gray')
                    ->placeholder(__('panels.app.menus.not_attached')),
                TextColumn::make('categories_count')
                    ->label(__('panels.app.menus.fields.categories'))
                    ->counts('categories'),
                IconColumn::make('is_published')
                    ->label(__('panels.app.menus.fields.is_published'))
                    ->boolean(),
            ])
            ->filters([
                TrashedFilter::make()->visible(fn (): bool => PanelContext::membership()?->isOwner() ?? false),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription(__('panels.app.menus.confirm.delete'))
                    ->using(function (Menu $record): bool {
                        app(MenuService::class)->delete($record);

                        return true;
                    }),
                RestoreAction::make()
                    ->using(function (Menu $record): bool {
                        ServiceCall::run(fn () => app(MenuService::class)->restore($record), statePath: null);

                        return true;
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CategoriesRelationManager::class,
            LocationsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $membership = PanelContext::membership();

        return parent::getEloquentQuery()
            ->with(['translations', 'locations.translations'])
            ->when(
                $membership === null,
                fn (Builder $query) => $query->whereRaw('1 = 0'),
                fn (Builder $query) => $query->editableBy($membership),
            );
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Menu ? ($record->translate()?->name ?? $record->slug) : '';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenus::route('/'),
            'create' => CreateMenu::route('/create'),
            'edit' => EditMenu::route('/{record}/edit'),
        ];
    }
}
