<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Locations\RelationManagers;

use App\Filament\App\Support\ServiceCall;
use App\Models\Location;
use App\Models\LocationSpecialHour;
use App\Models\User;
use App\Services\Locations\LocationHoursService;
use Carbon\CarbonImmutable;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Date-specific hours of a branch (holidays, Bayram). Visible to everyone who
 * may view the branch; changes require LocationPolicy::update on the branch
 * and go through LocationHoursService.
 */
class SpecialHoursRelationManager extends RelationManager
{
    protected static string $relationship = 'specialHours';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panels.app.hours.special');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('view', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')
                ->label(__('panels.app.hours.fields.date'))
                ->native(false)
                ->displayFormat('d.m.Y')
                ->required(),
            Toggle::make('is_closed')
                ->label(__('panels.app.hours.fields.is_closed'))
                ->live()
                ->default(false)
                ->inline(false),
            TimePicker::make('opens_at')
                ->label(__('panels.app.hours.fields.opens_at'))
                ->seconds(false)
                ->hidden(fn (Get $get): bool => (bool) $get('is_closed'))
                ->required(fn (Get $get): bool => ! $get('is_closed')),
            TimePicker::make('closes_at')
                ->label(__('panels.app.hours.fields.closes_at'))
                ->seconds(false)
                ->hidden(fn (Get $get): bool => (bool) $get('is_closed'))
                ->required(fn (Get $get): bool => ! $get('is_closed')),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->emptyStateHeading(__('panels.app.hours.special_empty'))
            ->emptyStateDescription(__('panels.app.hours.special_help'))
            ->columns([
                TextColumn::make('date')
                    ->label(__('panels.app.hours.fields.date'))
                    ->formatStateUsing(fn (LocationSpecialHour $record): string => $record->date->locale(app()->getLocale())->isoFormat('LL, dddd')),
                TextColumn::make('hours')
                    ->label(__('panels.app.hours.fields.hours'))
                    ->state(fn (LocationSpecialHour $record): string => $record->is_closed
                        ? __('panels.app.hours.closed')
                        : substr((string) $record->opens_at, 0, 5).' – '.substr((string) $record->closes_at, 0, 5))
                    ->badge()
                    ->color(fn (LocationSpecialHour $record): string => $record->is_closed ? 'danger' : 'gray'),
            ])
            ->filters([
                Filter::make('upcoming')
                    ->label(__('panels.app.hours.upcoming_only'))
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereDate(
                        'date',
                        '>=',
                        CarbonImmutable::now($this->location()->timezone)->toDateString(),
                    )),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('panels.app.hours.add_special'))
                    ->authorize(fn (): bool => $this->canChange())
                    ->using(fn (array $data): LocationSpecialHour => ServiceCall::run(
                        fn () => app(LocationHoursService::class)->saveSpecialHour($this->location(), $data),
                        statePath: null,
                    )),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize(fn (): bool => $this->canChange())
                    ->using(fn (LocationSpecialHour $record, array $data): LocationSpecialHour => ServiceCall::run(
                        fn () => app(LocationHoursService::class)->saveSpecialHour($this->location(), $data, $record),
                        statePath: null,
                    )),
                DeleteAction::make()
                    ->authorize(fn (): bool => $this->canChange())
                    ->using(function (LocationSpecialHour $record): bool {
                        app(LocationHoursService::class)->deleteSpecialHour($record);

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

    private function canChange(): bool
    {
        $user = auth()->user();

        return ! $this->isReadOnly() && $user instanceof User && $user->can('update', $this->location());
    }

    private function location(): Location
    {
        /** @var Location */
        return $this->getOwnerRecord();
    }
}
