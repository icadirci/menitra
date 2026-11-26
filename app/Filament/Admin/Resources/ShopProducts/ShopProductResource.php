<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ShopProducts;

use App\Filament\Admin\Forms\PlatformImageUpload;
use App\Filament\Admin\Forms\PlatformTranslatableFields;
use App\Filament\Admin\Navigation\AdminNavigationGroup;
use App\Filament\Admin\Resources\ShopProducts\Pages\CreateShopProduct;
use App\Filament\Admin\Resources\ShopProducts\Pages\EditShopProduct;
use App\Filament\Admin\Resources\ShopProducts\Pages\ListShopProducts;
use App\Filament\App\Forms\MoneyInput;
use App\Models\ShopProduct;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Products of the QR stand shop. Authorization: ShopProductPolicy (super admins).
 */
class ShopProductResource extends Resource
{
    protected static ?string $model = ShopProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Shop;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('shop.admin.products.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('shop.admin.products.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    PlatformImageUpload::make('image_path')
                        ->label(__('shop.admin.products.image'))
                        ->columnSpanFull(),
                    MoneyInput::make('price_amount')
                        ->label(__('shop.admin.products.price'))
                        ->helperText(__('shop.admin.settings.vat_help'))
                        ->required()
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (is_scalar($value) && preg_match('/[1-9]/', (string) $value) !== 1) {
                                $fail(__('shop.admin.products.price_positive'));
                            }
                        }),
                    TextInput::make('max_per_order')
                        ->label(__('shop.admin.products.max_per_order'))
                        ->integer()->minValue(1)->maxValue(10000)->default(200)->required(),
                    TextInput::make('stock_quantity')
                        ->label(__('shop.admin.products.stock'))
                        ->helperText(__('shop.admin.products.stock_help'))
                        ->integer()->minValue(-1000000)->maxValue(1000000),
                    Toggle::make('is_personalized')
                        ->label(__('shop.admin.products.personalized'))
                        ->helperText(__('shop.admin.products.personalized_help'))
                        ->default(true),
                    Toggle::make('is_active')
                        ->label(__('shop.admin.products.active'))
                        ->helperText(__('shop.admin.products.active_help'))
                        ->default(true),
                ]),
            PlatformTranslatableFields::make(fn (string $locale, bool $isDefault): array => [
                TextInput::make('name')
                    ->label(__('shop.admin.products.name'))
                    ->required($isDefault)
                    ->maxLength(120),
                Textarea::make('description')
                    ->label(__('shop.admin.products.description'))
                    ->rows(4)
                    ->maxLength(1000),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image_path')
                    ->label(__('shop.admin.products.image'))
                    ->state(fn (ShopProduct $record): ?string => $record->imageUrl()),
                TextColumn::make('name')
                    ->label(__('shop.admin.products.name'))
                    ->state(fn (ShopProduct $record): ?string => $record->translate()?->name),
                TextColumn::make('price_amount')
                    ->label(__('shop.admin.products.price'))
                    ->formatStateUsing(fn (ShopProduct $record): string => $record->price()->format()),
                TextColumn::make('stock_quantity')
                    ->label(__('shop.admin.products.stock'))
                    ->placeholder(__('plans.unlimited')),
                IconColumn::make('is_active')
                    ->label(__('shop.admin.products.active'))
                    ->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShopProducts::route('/'),
            'create' => CreateShopProduct::route('/create'),
            'edit' => EditShopProduct::route('/{record}/edit'),
        ];
    }
}
