<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\MenuItems\RelationManagers;

use App\Filament\App\Forms\MoneyInput;
use App\Filament\App\Forms\TranslatableFields;
use App\Filament\App\Support\PanelContext;
use App\Filament\App\Support\ServiceCall;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Services\Menus\MenuContentService;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Variants (sizes, portions) of an item; managed by whoever may edit the item.
 */
class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panels.app.variants.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return PanelContext::user()?->can('update', $ownerRecord) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TranslatableFields::make(fn (string $locale, bool $isDefault): array => [
                TextInput::make('name')
                    ->label(__('panels.app.variants.fields.name'))
                    ->placeholder(__('panels.app.variants.fields.name_placeholder'))
                    ->required($isDefault)
                    ->maxLength(80),
            ])->columnSpanFull(),
            MoneyInput::make('price')->label(__('panels.app.items.fields.price'))->required(),
            Toggle::make('is_default')->label(__('panels.app.variants.fields.is_default'))->inline(false),
            Toggle::make('is_available')->label(__('panels.app.items.fields.is_available'))->default(true)->inline(false),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations'))
            ->reorderable('sort_order', fn (): bool => $this->canChange())
            ->defaultSort('sort_order')
            ->emptyStateDescription(__('panels.app.variants.help'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('panels.app.variants.fields.name'))
                    ->state(fn (MenuItemVariant $record): ?string => $record->translate()?->name),
                TextColumn::make('price')
                    ->label(__('panels.app.items.fields.price'))
                    ->formatStateUsing(fn (int $state): string => Money::fromMinor($state, PanelContext::tenant()->currency)->format()),
                IconColumn::make('is_default')->label(__('panels.app.variants.fields.is_default'))->boolean(),
                IconColumn::make('is_available')->label(__('panels.app.items.fields.is_available'))->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->authorize(fn (): bool => $this->canChange())
                    ->using(fn (array $data): MenuItemVariant => ServiceCall::run(
                        fn () => app(MenuContentService::class)->saveVariant($this->item(), $data),
                        statePath: null,
                    )),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize(fn (): bool => $this->canChange())
                    ->fillForm(fn (MenuItemVariant $record): array => [
                        'price' => $record->price,
                        'is_default' => $record->is_default,
                        'is_available' => $record->is_available,
                        'translations' => TranslatableFields::fillFrom($record, ['name']),
                    ])
                    ->using(fn (MenuItemVariant $record, array $data): MenuItemVariant => ServiceCall::run(
                        fn () => app(MenuContentService::class)->saveVariant($this->item(), $data, $record),
                        statePath: null,
                    )),
                DeleteAction::make()
                    ->authorize(fn (): bool => $this->canChange())
                    ->using(function (MenuItemVariant $record): bool {
                        app(MenuContentService::class)->deleteVariant($record);

                        return true;
                    }),
            ]);
    }

    protected function canCreate(): bool
    {
        return $this->canChange();
    }

    protected function canEdit(Model $record): bool
    {
        return $this->canChange();
    }

    protected function canDelete(Model $record): bool
    {
        return $this->canChange();
    }

    protected function canReorder(): bool
    {
        return $this->canChange();
    }

    private function canChange(): bool
    {
        return PanelContext::user()?->can('update', $this->item()) ?? false;
    }

    private function item(): MenuItem
    {
        /** @var MenuItem */
        return $this->getOwnerRecord();
    }
}
