<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ExtraGroups\RelationManagers;

use App\Filament\App\Forms\MoneyInput;
use App\Filament\App\Forms\TranslatableFields;
use App\Filament\App\Support\PanelContext;
use App\Filament\App\Support\ServiceCall;
use App\Models\Extra;
use App\Models\ExtraGroup;
use App\Services\Menus\ExtraGroupService;
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

class ExtrasRelationManager extends RelationManager
{
    protected static string $relationship = 'extras';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panels.app.extras.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return PanelContext::user()?->can('update', $ownerRecord) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TranslatableFields::make(fn (string $locale, bool $isDefault): array => [
                TextInput::make('name')->label(__('panels.app.extras.fields.name'))->required($isDefault)->maxLength(80),
            ])->columnSpanFull(),
            MoneyInput::make('price_delta')
                ->label(__('panels.app.extras.fields.price_delta'))
                ->helperText(__('panels.app.extras.fields.price_delta_help'))
                ->allowNegative()
                ->default('0'),
            Toggle::make('is_available')->label(__('panels.app.items.fields.is_available'))->default(true)->inline(false),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations'))
            ->reorderable('sort_order', fn (): bool => $this->canChange())
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('panels.app.extras.fields.name'))
                    ->state(fn (Extra $record): ?string => $record->translate()?->name),
                TextColumn::make('price_delta')
                    ->label(__('panels.app.extras.fields.price_delta'))
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? '—' : ($state > 0 ? '+' : '').Money::fromMinor($state, PanelContext::tenant()->currency)->format()),
                IconColumn::make('is_available')->label(__('panels.app.items.fields.is_available'))->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->authorize(fn (): bool => $this->canChange())
                    ->using(fn (array $data): Extra => ServiceCall::run(
                        fn () => app(ExtraGroupService::class)->saveExtra($this->group(), $data),
                        statePath: null,
                    )),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize(fn (): bool => $this->canChange())
                    ->fillForm(fn (Extra $record): array => [
                        'price_delta' => $record->price_delta,
                        'is_available' => $record->is_available,
                        'translations' => TranslatableFields::fillFrom($record, ['name']),
                    ])
                    ->using(fn (Extra $record, array $data): Extra => ServiceCall::run(
                        fn () => app(ExtraGroupService::class)->saveExtra($this->group(), $data, $record),
                        statePath: null,
                    )),
                DeleteAction::make()
                    ->authorize(fn (): bool => $this->canChange())
                    ->using(function (Extra $record): bool {
                        app(ExtraGroupService::class)->deleteExtra($record);

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
        return PanelContext::user()?->can('update', $this->group()) ?? false;
    }

    private function group(): ExtraGroup
    {
        /** @var ExtraGroup */
        return $this->getOwnerRecord();
    }
}
