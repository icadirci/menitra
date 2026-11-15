<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menus\RelationManagers;

use App\Filament\App\Resources\Locations\LocationResource;
use App\Filament\App\Support\PanelContext;
use App\Filament\App\Support\ServiceCall;
use App\Models\Location;
use App\Models\LocationMenu;
use App\Models\Menu;
use App\Services\Menus\MenuService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Where a menu is shown (location_menu) and when it is served there.
 * Owner-only (MenuPolicy::manageLocations); writes go through MenuService.
 */
class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locationMenus';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panels.app.menus.locations_title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return PanelContext::user()?->can('manageLocations', $ownerRecord) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('location_id')
                ->label(__('panels.app.locations.singular'))
                ->options(fn (): array => $this->availableLocations())
                ->required()
                ->visibleOn('create'),
            Toggle::make('is_active')
                ->label(__('panels.app.menus.fields.attachment_active'))
                ->default(true),
            Repeater::make('hours')
                ->label(__('panels.app.menus.fields.serving_hours'))
                ->helperText(__('panels.app.menus.fields.serving_hours_help'))
                ->columns(3)
                ->defaultItems(0)
                ->maxItems(21)
                ->reorderable(false)
                ->addActionLabel(__('panels.app.hours.add'))
                ->schema([
                    Select::make('day_of_week')
                        ->label(__('panels.app.hours.fields.day'))
                        ->options(LocationResource::dayOptions())
                        ->required(),
                    TimePicker::make('starts_at')->label(__('panels.app.hours.fields.opens_at'))->seconds(false)->required(),
                    TimePicker::make('ends_at')->label(__('panels.app.hours.fields.closes_at'))->seconds(false)->required(),
                ])
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['location.translations', 'hours']))
            ->reorderable('sort_order', fn (): bool => $this->canChange())
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('location')
                    ->label(__('panels.app.locations.singular'))
                    ->state(fn (LocationMenu $record): string => $record->location?->translate()?->name ?? (string) $record->location?->slug),
                TextColumn::make('hours')
                    ->label(__('panels.app.menus.fields.serving_hours'))
                    ->state(fn (LocationMenu $record): string => $record->hours->isEmpty()
                        ? __('panels.app.menus.all_day')
                        : $record->hours->map(fn ($hour): string => LocationResource::dayOptions()[$hour->day_of_week].' '.substr((string) $hour->starts_at, 0, 5).'–'.substr((string) $hour->ends_at, 0, 5))->implode(', '))
                    ->wrap(),
                IconColumn::make('is_active')
                    ->label(__('panels.app.menus.fields.attachment_active'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('panels.app.menus.actions.attach'))
                    ->authorize(fn (): bool => $this->canChange())
                    ->using(fn (array $data): LocationMenu => ServiceCall::run(function () use ($data): LocationMenu {
                        $location = Location::query()->findOrFail($data['location_id']);

                        return app(MenuService::class)->attach($this->menu(), $location, $data);
                    }, statePath: null)),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize(fn (): bool => $this->canChange())
                    ->fillForm(fn (LocationMenu $record): array => [
                        'is_active' => $record->is_active,
                        'hours' => $record->hours->map(fn ($hour): array => [
                            'day_of_week' => $hour->day_of_week,
                            'starts_at' => substr((string) $hour->starts_at, 0, 5),
                            'ends_at' => substr((string) $hour->ends_at, 0, 5),
                        ])->all(),
                    ])
                    ->using(fn (LocationMenu $record, array $data): LocationMenu => ServiceCall::run(
                        fn () => app(MenuService::class)->updateAttachment($record, $data),
                        statePath: null,
                    )),
                DeleteAction::make()
                    ->label(__('panels.app.menus.actions.detach'))
                    ->authorize(fn (): bool => $this->canChange())
                    ->using(function (LocationMenu $record): bool {
                        app(MenuService::class)->detach($record);

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

    /**
     * @return array<int, string>
     */
    private function availableLocations(): array
    {
        return Location::query()
            ->with('translations')
            ->whereNotIn('id', $this->menu()->locationMenus()->select('location_id'))
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (Location $location): array => [$location->getKey() => $location->translate()?->name ?? $location->slug])
            ->all();
    }

    private function canChange(): bool
    {
        return PanelContext::user()?->can('manageLocations', $this->menu()) ?? false;
    }

    private function menu(): Menu
    {
        /** @var Menu */
        return $this->getOwnerRecord();
    }
}
