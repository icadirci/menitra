<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Enums\TenantRole;
use App\Filament\App\Resources\Menus\MenuResource;
use App\Filament\App\Support\PanelContext;
use App\Models\Menu;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Publication status of menus: published or draft, on how many active
 * branches it is served, how many items it has.
 *
 * owner: every menu. manager: menus attached to an assigned branch
 * (read-only unless the manager content rule lets them edit it).
 * branch_staff: hidden (no menu content work).
 */
class MenuStatusWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** @var array<int, true>|null ids of menus the member may edit */
    private ?array $editable = null;

    public static function canView(): bool
    {
        return in_array(PanelContext::membership()?->role, [TenantRole::Owner, TenantRole::Manager], true);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('panels.app.dashboard.menus.heading'))
            ->query(fn (): Builder => $this->menusQuery())
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('panels.app.dashboard.menus.empty'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('panels.app.dashboard.menus.columns.name'))
                    ->state(fn (Menu $record): string => $record->translate()?->name ?? $record->slug)
                    ->weight('medium'),
                TextColumn::make('is_published')
                    ->label(__('panels.app.dashboard.menus.columns.status'))
                    ->state(fn (Menu $record): string => __($record->is_published ? 'panels.app.dashboard.menus.published' : 'panels.app.dashboard.menus.draft'))
                    ->badge()
                    ->color(fn (Menu $record): string => $record->is_published ? 'success' : 'warning'),
                TextColumn::make('active_locations_count')
                    ->label(__('panels.app.dashboard.menus.columns.locations'))
                    ->numeric(),
                TextColumn::make('items_count')
                    ->label(__('panels.app.dashboard.menus.columns.items'))
                    ->numeric(),
            ])
            ->recordUrl(fn (Menu $record): ?string => $this->canEdit($record)
                ? MenuResource::getUrl('edit', ['record' => $record])
                : null);
    }

    private function menusQuery(): Builder
    {
        $membership = PanelContext::membership();

        if ($membership === null) {
            return Menu::query()->whereRaw('1 = 0');
        }

        return Menu::query()
            ->with('translations')
            ->withCount([
                'items',
                'locationMenus as active_locations_count' => fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->whereHas('location', fn (Builder $location) => $location->where('is_active', true)),
            ])
            ->when(! $membership->isOwner(), fn (Builder $query) => $query->whereHas(
                'locationMenus',
                fn (Builder $attachment) => $attachment->whereIn('location_id', $membership->accessibleLocations()->select('id')),
            ))
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * One query for all rows (the manager content rule as a scope), instead
     * of a policy query per row. Edit pages re-authorize through MenuPolicy.
     */
    private function canEdit(Menu $menu): bool
    {
        if ($this->editable === null) {
            $membership = PanelContext::membership();
            $this->editable = $membership === null
                ? []
                : array_fill_keys(Menu::query()->editableBy($membership)->pluck('id')->all(), true);
        }

        return isset($this->editable[(int) $menu->getKey()]);
    }
}
