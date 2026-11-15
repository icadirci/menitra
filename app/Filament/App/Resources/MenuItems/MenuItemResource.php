<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\MenuItems;

use App\Enums\Allergen;
use App\Enums\DietaryLabel;
use App\Enums\MediaType;
use App\Filament\App\Forms\MoneyInput;
use App\Filament\App\Forms\TenantImageUpload;
use App\Filament\App\Forms\TranslatableFields;
use App\Filament\App\Navigation\AppNavigationGroup;
use App\Filament\App\Resources\MenuItems\Pages\CreateMenuItem;
use App\Filament\App\Resources\MenuItems\Pages\EditMenuItem;
use App\Filament\App\Resources\MenuItems\Pages\ListMenuItems;
use App\Filament\App\Resources\MenuItems\RelationManagers\VariantsRelationManager;
use App\Filament\App\Support\PanelContext;
use App\Models\ExtraGroup;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Menus\MenuContentService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Products of the menus the member may edit (MenuItemPolicy follows
 * MenuPolicy). The chosen category is re-authorized on save.
 */
class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;

    protected static string|UnitEnum|null $navigationGroup = AppNavigationGroup::MenuManagement;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'items';

    public const array TRANSLATED = ['name', 'description'];

    public static function getModelLabel(): string
    {
        return __('panels.app.items.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panels.app.items.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('panels.app.items.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('panels.app.items.sections.general'))
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('menu_category_id')
                        ->label(__('panels.app.items.fields.category'))
                        ->options(fn (): array => self::categoryOptions())
                        ->searchable()
                        ->required(),
                    MoneyInput::make('price')
                        ->label(__('panels.app.items.fields.price'))
                        ->helperText(__('panels.app.items.fields.price_help')),
                    TranslatableFields::make(fn (string $locale, bool $isDefault): array => [
                        TextInput::make('name')
                            ->label(__('panels.app.items.fields.name'))
                            ->required($isDefault)
                            ->maxLength(160),
                        Textarea::make('description')
                            ->label(__('panels.app.items.fields.description'))
                            ->rows(3)
                            ->maxLength(1000),
                    ])->columnSpanFull(),
                    TenantImageUpload::make('image_path', MediaType::Item)
                        ->label(__('panels.app.items.fields.image'))
                        ->columnSpanFull(),
                ]),
            Section::make(__('panels.app.items.sections.display'))
                ->columnSpanFull()
                ->columns(4)
                ->schema([
                    Toggle::make('is_visible')->label(__('panels.app.items.fields.is_visible'))->default(true),
                    Toggle::make('is_available')->label(__('panels.app.items.fields.is_available'))->default(true),
                    Toggle::make('is_featured')->label(__('panels.app.items.fields.is_featured'))->default(false),
                    Toggle::make('is_popular')->label(__('panels.app.items.fields.is_popular'))->default(false),
                    TextInput::make('calories')->label(__('panels.app.items.fields.calories'))->integer()->minValue(0)->maxValue(10000),
                    TextInput::make('preparation_minutes')->label(__('panels.app.items.fields.preparation_minutes'))->integer()->minValue(0)->maxValue(600),
                ]),
            Section::make(__('panels.app.items.sections.details'))
                ->columnSpanFull()
                ->schema([
                    CheckboxList::make('dietary_labels')
                        ->label(__('panels.app.items.fields.dietary_labels'))
                        ->options(fn (): array => DietaryLabel::options())
                        ->columns(4),
                    CheckboxList::make('allergens')
                        ->label(__('panels.app.items.fields.allergens'))
                        ->options(fn (): array => Allergen::options())
                        ->columns(4),
                    Select::make('extra_group_ids')
                        ->label(__('panels.app.items.fields.extra_groups'))
                        ->helperText(__('panels.app.items.fields.extra_groups_help'))
                        ->multiple()
                        ->options(fn (): array => ExtraGroup::query()->with('translations')->orderBy('sort_order')->get()
                            ->mapWithKeys(fn (ExtraGroup $group): array => [$group->getKey() => $group->translate()?->name ?? '#'.$group->getKey()])
                            ->all()),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('panels.app.items.fields.name'))
                    ->state(fn (MenuItem $record): ?string => $record->translate()?->name)
                    ->description(fn (MenuItem $record): string => ($record->category?->menu?->translate()?->name ?? '').' · '.($record->category?->translate()?->name ?? ''))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $q) => $q->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%'),
                    )),
                TextColumn::make('price')
                    ->label(__('panels.app.items.fields.price'))
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Money::fromMinor($state, PanelContext::tenant()->currency)->format())
                    ->placeholder('—'),
                TextColumn::make('variants_count')
                    ->label(__('panels.app.variants.plural'))
                    ->counts('variants'),
                IconColumn::make('is_visible')->label(__('panels.app.items.fields.is_visible'))->boolean(),
                IconColumn::make('is_available')->label(__('panels.app.items.fields.is_available'))->boolean(),
                IconColumn::make('is_featured')->label(__('panels.app.items.fields.is_featured'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('menu')
                    ->label(__('panels.app.menus.singular'))
                    ->options(fn (): array => self::editableMenus()->mapWithKeys(fn (Menu $menu): array => [$menu->getKey() => $menu->translate()?->name ?? $menu->slug])->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $menuId) => $query->whereHas('category', fn (Builder $q) => $q->where('menu_id', $menuId)),
                    )),
                SelectFilter::make('menu_category_id')
                    ->label(__('panels.app.items.fields.category'))
                    ->options(fn (): array => self::categoryOptions()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(function (MenuItem $record): bool {
                        app(MenuContentService::class)->deleteItem($record);

                        return true;
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [VariantsRelationManager::class];
    }

    public static function getEloquentQuery(): Builder
    {
        $membership = PanelContext::membership();

        if ($membership === null) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->with(['translations', 'category.translations', 'category.menu.translations'])
            ->whereHas('category', fn (Builder $category) => $category->whereHas(
                'menu',
                fn (Builder $menu) => $menu->whereNull('menus.deleted_at')->editableBy($membership),
            ));
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof MenuItem ? (string) $record->translate()?->name : '';
    }

    /**
     * Categories of editable menus, grouped by menu.
     *
     * @return array<string, array<int, string>>
     */
    public static function categoryOptions(): array
    {
        $options = [];

        foreach (self::editableMenus() as $menu) {
            $label = $menu->translate()?->name ?? $menu->slug;

            foreach ($menu->categories as $category) {
                $options[$label][$category->getKey()] = $category->translate()?->name ?? '#'.$category->getKey();
            }
        }

        return $options;
    }

    /**
     * Server-side check of a submitted category: its menu must be editable.
     */
    public static function canUseCategory(mixed $categoryId): bool
    {
        $category = is_numeric($categoryId) ? MenuCategory::query()->with('menu')->find((int) $categoryId) : null;
        $user = PanelContext::user();

        return $category?->menu !== null
            && ! $category->menu->trashed()
            && $user instanceof User
            && $user->can('update', $category->menu);
    }

    /**
     * @return Collection<int, Menu>
     */
    private static function editableMenus(): Collection
    {
        $membership = PanelContext::membership();

        if ($membership === null) {
            return collect();
        }

        return Menu::query()
            ->editableBy($membership)
            ->with(['translations', 'categories.translations'])
            ->orderBy('sort_order')
            ->get();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenuItems::route('/'),
            'create' => CreateMenuItem::route('/create'),
            'edit' => EditMenuItem::route('/{record}/edit'),
        ];
    }
}
