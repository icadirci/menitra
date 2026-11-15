<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menus\RelationManagers;

use App\Enums\MediaType;
use App\Filament\App\Forms\TenantImageUpload;
use App\Filament\App\Forms\TranslatableFields;
use App\Filament\App\Support\PanelContext;
use App\Filament\App\Support\ServiceCall;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Services\Menus\MenuContentService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
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
 * Categories of a menu. Everyone who may edit the menu (MenuPolicy::update)
 * manages them; writes go through MenuContentService.
 */
class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';

    private const array TRANSLATED = ['name', 'description'];

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panels.app.categories.plural');
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
                    ->label(__('panels.app.categories.fields.name'))
                    ->required($isDefault)
                    ->maxLength(120),
                Textarea::make('description')
                    ->label(__('panels.app.categories.fields.description'))
                    ->rows(2)
                    ->maxLength(500),
            ])->columnSpanFull(),
            Toggle::make('is_visible')
                ->label(__('panels.app.categories.fields.is_visible'))
                ->default(true),
            TenantImageUpload::make('image_path', MediaType::Category)
                ->label(__('panels.app.categories.fields.image'))
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations')->withCount('items'))
            ->reorderable('sort_order', fn (): bool => $this->canChange())
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('panels.app.categories.fields.name'))
                    ->state(fn (MenuCategory $record): ?string => $record->translate()?->name),
                TextColumn::make('items_count')
                    ->label(__('panels.app.categories.fields.items')),
                IconColumn::make('is_visible')
                    ->label(__('panels.app.categories.fields.is_visible'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->authorize(fn (): bool => $this->canChange())
                    ->using(fn (array $data): MenuCategory => ServiceCall::run(
                        fn () => app(MenuContentService::class)->saveCategory($this->menu(), $data),
                        statePath: null,
                    )),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize(fn (): bool => $this->canChange())
                    ->fillForm(fn (MenuCategory $record): array => [
                        'is_visible' => $record->is_visible,
                        'image_path' => $record->image_path,
                        'translations' => TranslatableFields::fillFrom($record, self::TRANSLATED),
                    ])
                    ->using(fn (MenuCategory $record, array $data): MenuCategory => ServiceCall::run(
                        fn () => app(MenuContentService::class)->saveCategory($this->menu(), $data, $record),
                        statePath: null,
                    )),
                DeleteAction::make()
                    ->authorize(fn (): bool => $this->canChange())
                    ->modalDescription(__('panels.app.categories.confirm.delete'))
                    ->using(function (MenuCategory $record): bool {
                        app(MenuContentService::class)->deleteCategory($record);

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
        return PanelContext::user()?->can('update', $this->menu()) ?? false;
    }

    private function menu(): Menu
    {
        /** @var Menu */
        return $this->getOwnerRecord();
    }
}
