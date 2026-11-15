<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Filament\App\Navigation\AppNavigationGroup;
use App\Filament\App\Support\PanelContext;
use App\Filament\App\Support\ServiceCall;
use App\Models\Location;
use App\Models\LocationItemState;
use App\Models\MenuItem;
use App\Services\Locations\StockService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use UnitEnum;

/**
 * Branch operations: which items are sold out at a branch. Every role may use
 * it for its accessible branches (LocationPolicy::manageStock); the selected
 * branch is re-checked on every request. Writes go through StockService.
 */
class ManageStock extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = AppNavigationGroup::Locations;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'stock';

    /** @var Collection<int, Location>|null */
    private ?Collection $locations = null;

    public static function canAccess(): bool
    {
        $membership = PanelContext::membership();

        return $membership !== null && $membership->accessibleLocations()->exists();
    }

    public static function getNavigationLabel(): string
    {
        return __('panels.app.stock.navigation');
    }

    public function getTitle(): string
    {
        return __('panels.app.stock.title');
    }

    public function getSubheading(): ?string
    {
        return __('panels.app.stock.subheading');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->itemsQuery())
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('name')
                    ->label(__('panels.app.items.fields.name'))
                    ->state(fn (MenuItem $record): ?string => $record->translate()?->name)
                    ->description(fn (MenuItem $record): string => ($record->category?->menu?->translate()?->name ?? '').' · '.($record->category?->translate()?->name ?? ''))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $q) => $q->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%'),
                    )),
                TextColumn::make('status')
                    ->label(__('panels.app.stock.status'))
                    ->state(fn (MenuItem $record): string => $this->statusLabel($record))
                    ->badge()
                    ->color(fn (MenuItem $record): string => $this->state($record)?->isEffectivelySoldOut() ? 'danger' : ($record->is_available ? 'success' : 'gray')),
            ])
            ->filters([
                SelectFilter::make('location')
                    ->label(__('panels.app.locations.singular'))
                    ->options(fn (): array => $this->locations()->mapWithKeys(fn (Location $location): array => [$location->getKey() => $location->translate()?->name ?? $location->slug])->all())
                    ->default(fn (): ?int => $this->locations()->first()?->getKey())
                    ->selectablePlaceholder(false)
                    // Applied in itemsQuery(), where the value is re-checked.
                    ->query(fn (Builder $query): Builder => $query),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                Action::make('available')
                    ->label(__('panels.app.stock.actions.available'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (MenuItem $record): bool => (bool) $this->state($record)?->isEffectivelySoldOut())
                    ->authorize(fn (): bool => $this->canManage())
                    ->action(fn (MenuItem $record) => $this->run(fn (StockService $stock) => $stock->markAvailable($this->location(), $record))),
                ActionGroup::make([
                    Action::make('soldOutToday')
                        ->label(__('panels.app.stock.actions.sold_out_today'))
                        ->authorize(fn (): bool => $this->canManage())
                        ->action(fn (MenuItem $record) => $this->run(fn (StockService $stock) => $stock->markSoldOut(
                            $this->location(),
                            $record,
                            $stock->endOfToday($this->location()),
                        ))),
                    Action::make('soldOutUntil')
                        ->label(__('panels.app.stock.actions.sold_out_until'))
                        ->authorize(fn (): bool => $this->canManage())
                        ->schema([
                            DateTimePicker::make('until')
                                ->label(__('panels.app.stock.fields.until'))
                                ->seconds(false)
                                ->timezone(fn (): string => $this->location()->timezone)
                                ->minDate(now())
                                ->required(),
                        ])
                        ->action(fn (MenuItem $record, array $data) => $this->run(fn (StockService $stock) => $stock->markSoldOut(
                            $this->location(),
                            $record,
                            CarbonImmutable::parse($data['until'], 'UTC'),
                        ))),
                    Action::make('soldOutIndefinitely')
                        ->label(__('panels.app.stock.actions.sold_out_indefinitely'))
                        ->authorize(fn (): bool => $this->canManage())
                        ->action(fn (MenuItem $record) => $this->run(fn (StockService $stock) => $stock->markSoldOut($this->location(), $record))),
                ])
                    ->label(__('panels.app.stock.actions.mark_sold_out'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->button(),
            ]);
    }

    /**
     * Visible items of the menus attached to the selected branch, with that
     * branch's sold-out state.
     */
    private function itemsQuery(): Builder
    {
        $location = $this->location();

        return MenuItem::query()
            ->with([
                'translations',
                'category.translations',
                'category.menu.translations',
                'locationStates' => fn (HasMany $query) => $query->where('location_id', $location->getKey()),
            ])
            ->whereHas('category', fn (Builder $category) => $category->whereHas(
                'menu',
                fn (Builder $menu) => $menu->whereNull('menus.deleted_at')->whereHas(
                    'locationMenus',
                    fn (Builder $attachment) => $attachment->where('location_id', $location->getKey()),
                ),
            ))
            ->orderBy('menu_category_id')
            ->orderBy('sort_order');
    }

    private function statusLabel(MenuItem $record): string
    {
        $state = $this->state($record);

        if ($state?->isEffectivelySoldOut()) {
            return $state->sold_out_until === null
                ? __('panels.app.stock.sold_out_indefinitely')
                : __('panels.app.stock.sold_out_until', [
                    'time' => $state->sold_out_until->setTimezone($this->location()->timezone)->locale(app()->getLocale())->isoFormat('D MMM HH:mm'),
                ]);
        }

        return $record->is_available ? __('panels.app.stock.on_sale') : __('panels.app.stock.unavailable');
    }

    private function state(MenuItem $record): ?LocationItemState
    {
        return $record->locationStates->first();
    }

    /**
     * @param  \Closure(StockService): mixed  $callback
     */
    private function run(\Closure $callback): void
    {
        ServiceCall::run(fn () => $callback(app(StockService::class)), statePath: null);

        Notification::make()->success()->title(__('panels.app.stock.saved'))->send();
    }

    private function canManage(): bool
    {
        return PanelContext::user()?->can('manageStock', $this->location()) ?? false;
    }

    /**
     * The selected branch; the filter value comes from the client, so it is
     * looked up among the member's accessible branches only.
     */
    private function location(): Location
    {
        $selected = $this->tableFilters['location']['value'] ?? null;

        return $this->locations()->firstWhere('id', (int) $selected)
            ?? $this->locations()->first()
            ?? abort(403);
    }

    /**
     * @return Collection<int, Location>
     */
    private function locations(): Collection
    {
        return $this->locations ??= PanelContext::membership()?->accessibleLocations()
            ->with('translations')
            ->orderBy('sort_order')
            ->get() ?? new Collection;
    }
}
