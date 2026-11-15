<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ExtraGroups;

use App\Enums\ExtraSelectionType;
use App\Filament\App\Forms\TranslatableFields;
use App\Filament\App\Navigation\AppNavigationGroup;
use App\Filament\App\Resources\ExtraGroups\Pages\CreateExtraGroup;
use App\Filament\App\Resources\ExtraGroups\Pages\EditExtraGroup;
use App\Filament\App\Resources\ExtraGroups\Pages\ListExtraGroups;
use App\Filament\App\Resources\ExtraGroups\RelationManagers\ExtrasRelationManager;
use App\Models\ExtraGroup;
use App\Services\Menus\ExtraGroupService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Reusable extra groups (owner-only, ExtraGroupPolicy). Writes go through
 * ExtraGroupService.
 */
class ExtraGroupResource extends Resource
{
    protected static ?string $model = ExtraGroup::class;

    protected static string|UnitEnum|null $navigationGroup = AppNavigationGroup::MenuManagement;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'extra-groups';

    public static function getModelLabel(): string
    {
        return __('panels.app.extras.group_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panels.app.extras.group_plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('panels.app.extras.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(4)
                ->schema([
                    TranslatableFields::make(fn (string $locale, bool $isDefault): array => [
                        TextInput::make('name')
                            ->label(__('panels.app.extras.fields.group_name'))
                            ->placeholder(__('panels.app.extras.fields.group_name_placeholder'))
                            ->required($isDefault)
                            ->maxLength(120),
                    ])->columnSpanFull(),
                    Select::make('selection_type')
                        ->label(__('panels.app.extras.fields.selection_type'))
                        ->options(ExtraSelectionType::class)
                        ->default(ExtraSelectionType::Single)
                        ->live()
                        ->required(),
                    TextInput::make('min_select')
                        ->label(__('panels.app.extras.fields.min_select'))
                        ->integer()
                        ->minValue(0)
                        ->maxValue(20)
                        ->default(0)
                        ->required(),
                    TextInput::make('max_select')
                        ->label(__('panels.app.extras.fields.max_select'))
                        ->helperText(__('panels.app.extras.fields.max_select_help'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(20)
                        ->hidden(fn (Get $get): bool => self::isSingle($get('selection_type'))),
                    Toggle::make('is_required')
                        ->label(__('panels.app.extras.fields.is_required'))
                        ->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations')->withCount(['extras', 'items']))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('panels.app.extras.fields.group_name'))
                    ->state(fn (ExtraGroup $record): ?string => $record->translate()?->name),
                TextColumn::make('selection_type')->label(__('panels.app.extras.fields.selection_type'))->badge(),
                TextColumn::make('extras_count')->label(__('panels.app.extras.plural')),
                TextColumn::make('items_count')->label(__('panels.app.items.plural')),
                IconColumn::make('is_required')->label(__('panels.app.extras.fields.is_required'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription(__('panels.app.extras.confirm.delete'))
                    ->using(function (ExtraGroup $record): bool {
                        app(ExtraGroupService::class)->deleteGroup($record);

                        return true;
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [ExtrasRelationManager::class];
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof ExtraGroup ? (string) $record->translate()?->name : '';
    }

    private static function isSingle(mixed $value): bool
    {
        return $value === ExtraSelectionType::Single || $value === ExtraSelectionType::Single->value;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExtraGroups::route('/'),
            'create' => CreateExtraGroup::route('/create'),
            'edit' => EditExtraGroup::route('/{record}/edit'),
        ];
    }
}
