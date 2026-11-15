<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Filament\App\Pages\ManageStock;
use App\Filament\App\Support\PanelContext;
use App\Models\Location;
use App\Models\QrCode;
use App\Services\Dashboard\BusinessDashboard;
use App\Services\Menu\MenuUrlGenerator;
use App\Services\QrCodes\QrCodeImage;
use App\Tenancy\TenantContext;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One row per branch the member may act on (accessibleLocations(): owners
 * every branch, managers and branch staff their assigned ones): public menu
 * link (MenuUrlGenerator), items sold out right now (with a link to the
 * stock page for that branch) and the branch's default QR code with SVG/PNG
 * downloads. Every role sees it.
 *
 * Constant number of queries: translations, the default code and the
 * sold-out count are eager loaded / counted in the main query.
 */
class BranchOverviewWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** @var array<int, true>|null ids of the member's accessible branches */
    private ?array $accessible = null;

    public static function canView(): bool
    {
        return PanelContext::membership() !== null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('panels.app.dashboard.branches.heading'))
            ->description(fn (): string => app(BusinessDashboard::class)->isMenuOnline()
                ? __('panels.app.dashboard.branches.description')
                : __('panels.app.dashboard.branches.offline'))
            ->query(fn (): Builder => $this->locationsQuery())
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('panels.app.dashboard.branches.empty'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('panels.app.dashboard.branches.columns.name'))
                    ->state(fn (Location $record): string => $record->translate()?->name ?? $record->slug)
                    ->description(fn (Location $record): ?string => $record->is_default ? __('panels.app.dashboard.branches.default') : null)
                    ->weight('medium'),
                TextColumn::make('is_active')
                    ->label(__('panels.app.dashboard.branches.columns.status'))
                    ->state(fn (Location $record): string => __($record->is_active ? 'panels.app.dashboard.branches.active' : 'panels.app.dashboard.branches.inactive'))
                    ->badge()
                    ->color(fn (Location $record): string => $record->is_active ? 'success' : 'gray'),
                TextColumn::make('sold_out_count')
                    ->label(__('panels.app.dashboard.branches.columns.sold_out'))
                    ->state(fn (Location $record): string => (int) $record->sold_out_count === 0
                        ? __('panels.app.dashboard.branches.sold_out_none')
                        : __('panels.app.dashboard.branches.sold_out_count', ['count' => (int) $record->sold_out_count]))
                    ->badge()
                    ->color(fn (Location $record): string => (int) $record->sold_out_count === 0 ? 'success' : 'danger')
                    ->url(fn (Location $record): string => ManageStock::getUrl(['filters' => ['location' => ['value' => $record->getKey()]]])),
                TextColumn::make('public_url')
                    ->label(__('panels.app.dashboard.branches.columns.public_url'))
                    ->state(fn (Location $record): string => $this->publicUrl($record))
                    ->url(fn (Location $record): string => $this->publicUrl($record), shouldOpenInNewTab: true)
                    ->copyable()
                    ->copyMessage(__('panels.app.dashboard.branches.copied'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('primary'),
                TextColumn::make('default_qr')
                    ->label(__('panels.app.dashboard.branches.columns.qr'))
                    ->state(fn (Location $record): string => $record->defaultQrCode?->label ?? __('panels.app.dashboard.branches.no_qr'))
                    ->description(fn (Location $record): ?string => $record->defaultQrCode?->code)
                    ->color(fn (Location $record): ?string => $record->defaultQrCode === null ? 'gray' : null),
            ])
            ->recordActions([
                $this->downloadAction('svg'),
                $this->downloadAction('png'),
            ]);
    }

    /**
     * Downloads are part of viewing a code (QrCodePolicy::view): every role
     * on its own branches. The code comes from the row, which is itself
     * limited to accessible branches.
     */
    private function downloadAction(string $format): Action
    {
        return Action::make('download'.ucfirst($format))
            ->label(__("panels.app.qr_codes.actions.download_{$format}"))
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->visible(fn (Location $record): bool => $record->defaultQrCode !== null)
            // Per row without extra queries: the code belongs to the row's
            // branch and that branch is one of the member's (ids loaded once).
            ->authorize(fn (Location $record): bool => $record->defaultQrCode instanceof QrCode
                && (int) $record->defaultQrCode->location_id === (int) $record->getKey()
                && $this->isAccessible($record))
            ->action(function (Location $record) use ($format): StreamedResponse {
                $qr = $record->defaultQrCode;
                // Full policy check when the download actually runs.
                Gate::authorize('view', $qr);
                $images = app(QrCodeImage::class);
                $content = $format === 'svg' ? $images->svg($qr) : $images->png($qr);

                return response()->streamDownload(
                    function () use ($content): void {
                        echo $content;
                    },
                    $images->filename($qr, $format),
                    ['Content-Type' => $format === 'svg' ? 'image/svg+xml' : 'image/png'],
                );
            });
    }

    private function isAccessible(Location $location): bool
    {
        $this->accessible ??= array_fill_keys(
            PanelContext::membership()?->accessibleLocations()->pluck('id')->all() ?? [],
            true,
        );

        return isset($this->accessible[(int) $location->getKey()]);
    }

    private function locationsQuery(): Builder
    {
        $membership = PanelContext::membership();

        if ($membership === null) {
            return Location::query()->whereRaw('1 = 0');
        }

        return $membership->accessibleLocations()
            ->with(['translations', 'defaultQrCode'])
            // Sold out now: indefinitely or until a time still in the future,
            // for items of menus that still exist.
            ->withCount(['itemStates as sold_out_count' => fn (Builder $query) => $query
                ->where('is_sold_out', true)
                ->where(fn (Builder $until) => $until->whereNull('sold_out_until')->orWhere('sold_out_until', '>', now()))
                ->whereHas('item.category.menu')])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    private function publicUrl(Location $location): string
    {
        return app(MenuUrlGenerator::class)->location(app(TenantContext::class)->tenant()->slug, $location->slug);
    }
}
