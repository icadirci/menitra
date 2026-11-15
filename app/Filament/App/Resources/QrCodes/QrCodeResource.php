<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\QrCodes;

use App\Enums\QrBackground;
use App\Filament\App\Navigation\AppNavigationGroup;
use App\Filament\App\Resources\QrCodes\Pages\CreateQrCode;
use App\Filament\App\Resources\QrCodes\Pages\EditQrCode;
use App\Filament\App\Resources\QrCodes\Pages\ListQrCodes;
use App\Filament\App\Support\PanelContext;
use App\Models\Location;
use App\Models\LocationMenu;
use App\Models\QrCode;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\QrCodes\QrCodeImage;
use App\Services\QrCodes\QrCodeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Stable QR codes (/q/{code}) of the member's branches. Authorization:
 * QrCodePolicy (owners: everything; managers: create/edit on assigned
 * branches; branch staff: view and download on assigned branches). The query
 * is limited to accessible branches, so another branch's code is a 404; the
 * submitted branch is re-checked with canUseLocation(), the menu by
 * QrCodeService (must be attached to that branch).
 */
class QrCodeResource extends Resource
{
    protected static ?string $model = QrCode::class;

    protected static string|UnitEnum|null $navigationGroup = AppNavigationGroup::Locations;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'qr-codes';

    public static function getModelLabel(): string
    {
        return __('panels.app.qr_codes.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panels.app.qr_codes.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('panels.app.qr_codes.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('panels.app.qr_codes.sections.target'))
                ->description(__('panels.app.qr_codes.sections.target_help'))
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('label')
                        ->label(__('panels.app.qr_codes.fields.label'))
                        ->helperText(__('panels.app.qr_codes.fields.label_help'))
                        ->required()
                        ->maxLength(120)
                        ->columnSpanFull(),
                    Select::make('location_id')
                        ->label(__('panels.app.qr_codes.fields.location'))
                        ->options(fn (): array => self::locationOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('menu_id', null)),
                    Select::make('menu_id')
                        ->label(__('panels.app.qr_codes.fields.menu'))
                        ->helperText(__('panels.app.qr_codes.fields.menu_help'))
                        ->placeholder(__('panels.app.qr_codes.fields.menu_none'))
                        ->options(fn (Get $get): array => self::menuOptions($get('location_id'))),
                    Toggle::make('is_active')
                        ->label(__('panels.app.qr_codes.fields.is_active'))
                        ->helperText(__('panels.app.qr_codes.fields.is_active_help'))
                        ->default(true),
                ]),
            Section::make(__('panels.app.qr_codes.sections.image'))
                ->columnSpanFull()
                ->visibleOn('edit')
                ->schema([
                    Image::make(
                        fn (?QrCode $record): string => $record === null ? '' : app(QrCodeImage::class)->svgDataUri($record),
                        __('panels.app.qr_codes.preview_alt'),
                    )->imageSize(200),
                    TextInput::make('stable_url')
                        ->label(__('panels.app.qr_codes.fields.url'))
                        ->helperText(__('panels.app.qr_codes.fields.url_help'))
                        ->formatStateUsing(fn (?QrCode $record): ?string => $record === null ? null : app(QrCodeImage::class)->url($record))
                        ->disabled()
                        ->dehydrated(false)
                        ->copyable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('label')
                    ->label(__('panels.app.qr_codes.fields.label'))
                    ->description(fn (QrCode $record): string => app(QrCodeImage::class)->url($record))
                    ->searchable(),
                TextColumn::make('location_name')
                    ->label(__('panels.app.qr_codes.fields.location'))
                    ->state(fn (QrCode $record): string => $record->location === null
                        ? __('panels.app.qr_codes.deleted_location')
                        : ($record->location->translate()?->name ?? $record->location->slug)),
                TextColumn::make('menu_name')
                    ->label(__('panels.app.qr_codes.fields.target'))
                    ->state(fn (QrCode $record): string => $record->menu?->translate()?->name ?? __('panels.app.qr_codes.fields.menu_none')),
                IconColumn::make('is_active')
                    ->label(__('panels.app.qr_codes.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('location_id')
                    ->label(__('panels.app.qr_codes.fields.location'))
                    ->options(fn (): array => self::locationOptions()),
            ])
            ->recordActions([
                self::downloadAction('svg'),
                self::downloadAction('png'),
                self::downloadOnBackgroundAction(),
                EditAction::make(),
                self::toggleActiveAction(),
                DeleteAction::make()
                    ->modalDescription(__('panels.app.qr_codes.confirm.delete'))
                    ->using(function (QrCode $record): bool {
                        app(QrCodeService::class)->delete($record);

                        return true;
                    }),
            ]);
    }

    /**
     * Downloads are part of viewing: every role on its accessible branches.
     */
    public static function downloadAction(string $format): Action
    {
        return Action::make('download'.ucfirst($format))
            ->label(__("panels.app.qr_codes.actions.download_{$format}"))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->authorize('view')
            ->action(function (QrCode $record) use ($format): StreamedResponse {
                $images = app(QrCodeImage::class);
                $content = $format === 'svg' ? $images->svg($record) : $images->png($record);

                return response()->streamDownload(
                    function () use ($content): void {
                        echo $content;
                    },
                    $images->filename($record, $format),
                    ['Content-Type' => $format === 'svg' ? 'image/svg+xml' : 'image/png'],
                );
            });
    }

    /**
     * PNG of the code placed on one of the platform's printable backgrounds,
     * chosen in a modal with a live preview. Also part of viewing.
     */
    public static function downloadOnBackgroundAction(): Action
    {
        return Action::make('downloadOnBackground')
            ->label(__('panels.app.qr_codes.actions.download_on_background'))
            ->icon(Heroicon::OutlinedPhoto)
            ->color('gray')
            ->authorize('view')
            ->modalHeading(__('panels.app.qr_codes.background_modal.heading'))
            ->modalDescription(__('panels.app.qr_codes.background_modal.description'))
            ->modalSubmitActionLabel(__('panels.app.qr_codes.background_modal.submit'))
            ->modalWidth('md')
            ->schema(fn (QrCode $record): array => [
                Select::make('background')
                    ->label(__('panels.app.qr_codes.background_modal.background'))
                    ->options(QrBackground::options())
                    ->default(QrBackground::cases()[0]->value)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live(),
                Image::make(
                    fn (Get $get): string => app(QrCodeImage::class)->backgroundPreviewDataUri(
                        $record,
                        QrBackground::tryFrom((string) $get('background')) ?? QrBackground::cases()[0],
                    ),
                    __('panels.app.qr_codes.preview_alt'),
                )
                    // Natural aspect ratio for portrait and landscape backgrounds.
                    ->imageWidth('auto')
                    ->imageHeight('auto')
                    ->extraAttributes(['style' => 'max-width: 100%; max-height: 20rem'])
                    ->alignCenter(),
            ])
            ->action(function (QrCode $record, array $data): StreamedResponse {
                // The Select only accepts its options; from() fails closed on anything else.
                $background = QrBackground::from((string) ($data['background'] ?? ''));
                $images = app(QrCodeImage::class);
                $content = $images->pngOnBackground($record, $background);

                return response()->streamDownload(
                    function () use ($content): void {
                        echo $content;
                    },
                    $images->filename($record, 'png', $background),
                    ['Content-Type' => 'image/png'],
                );
            });
    }

    public static function toggleActiveAction(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (QrCode $record): string => __($record->is_active ? 'panels.app.qr_codes.actions.deactivate' : 'panels.app.qr_codes.actions.activate'))
            ->icon(fn (QrCode $record): Heroicon => $record->is_active ? Heroicon::OutlinedPauseCircle : Heroicon::OutlinedPlayCircle)
            ->color(fn (QrCode $record): string => $record->is_active ? 'warning' : 'success')
            ->authorize('update')
            ->requiresConfirmation()
            ->modalDescription(fn (QrCode $record): ?string => $record->is_active ? __('panels.app.qr_codes.confirm.deactivate') : null)
            ->action(function (QrCode $record, Action $action): void {
                app(QrCodeService::class)->setActive($record, ! $record->is_active);
                $action->successNotificationTitle(__('panels.app.qr_codes.notifications.saved'))->success();
            });
    }

    /**
     * Server-side check of a submitted branch: one of the tenant's branches
     * (tenant scope) that the member may create codes for.
     */
    public static function usableLocation(mixed $locationId): ?Location
    {
        $location = is_numeric($locationId) ? Location::query()->find((int) $locationId) : null;
        $user = PanelContext::user();

        return $location !== null && $user instanceof User && $user->can('manageAt', [QrCode::class, $location])
            ? $location
            : null;
    }

    /**
     * Only codes of branches the member may act on (QrCodePolicy re-checks
     * every record). Owners also see codes of deleted branches.
     */
    public static function getEloquentQuery(): Builder
    {
        $membership = self::membership();

        return parent::getEloquentQuery()
            ->with(['location.translations', 'menu.translations'])
            ->when(
                $membership === null,
                fn (Builder $query) => $query->whereRaw('1 = 0'),
                fn (Builder $query) => $query->when(
                    ! $membership->role->reachesAllLocations(),
                    fn (Builder $query) => $query->whereIn('location_id', $membership->accessibleLocations()->select('id')),
                ),
            );
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof QrCode ? (string) ($record->label ?: $record->code) : '';
    }

    /**
     * Branches the member may create codes for.
     *
     * @return array<int, string>
     */
    public static function locationOptions(): array
    {
        $membership = self::membership();

        if ($membership === null) {
            return [];
        }

        return $membership->accessibleLocations()
            ->with('translations')
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (Location $location): array => [$location->getKey() => $location->translate()?->name ?? $location->slug])
            ->all();
    }

    /**
     * Menus attached to the chosen branch (an option list only; the
     * attachment is enforced by QrCodeService).
     *
     * @return array<int, string>
     */
    public static function menuOptions(mixed $locationId): array
    {
        if (! is_numeric($locationId) || ! array_key_exists((int) $locationId, self::locationOptions())) {
            return [];
        }

        return LocationMenu::query()
            ->where('location_id', (int) $locationId)
            ->whereHas('menu')
            ->with('menu.translations')
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (LocationMenu $attachment): array => [
                $attachment->menu_id => $attachment->menu->translate()?->name ?? $attachment->menu->slug,
            ])
            ->all();
    }

    public static function membership(): ?TenantMembership
    {
        return PanelContext::membership();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQrCodes::route('/'),
            'create' => CreateQrCode::route('/create'),
            'edit' => EditQrCode::route('/{record}/edit'),
        ];
    }
}
