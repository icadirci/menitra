<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Locations\RelationManagers;

use App\Enums\PlanFeature;
use App\Enums\QrBackground;
use App\Filament\App\Resources\QrCodes\QrCodeResource;
use App\Filament\App\Support\ServiceCall;
use App\Models\Location;
use App\Models\LocationTable;
use App\Models\QrCode;
use App\Models\User;
use App\Services\Locations\LocationTableService;
use App\Services\QrCodes\QrCodeImage;
use App\Services\Subscription\FeatureGate;
use App\Tenancy\TenantContext;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Tables of a branch, each with its own QR code. Visible to everyone who may
 * view the branch (and so download its codes); creating and renaming need
 * LocationPolicy::update, deleting LocationPolicy::deleteTable (owner). Writes
 * go through LocationTableService, which also enforces max_tables.
 */
class TablesRelationManager extends RelationManager
{
    protected static string $relationship = 'tables';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panels.app.tables.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('view', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('panels.app.tables.fields.name'))
                ->helperText(__('panels.app.tables.fields.name_help'))
                ->required()
                ->maxLength(LocationTableService::MAX_NAME_LENGTH),
            TextInput::make('sort_order')
                ->label(__('panels.app.tables.fields.sort_order'))
                ->integer()
                ->visibleOn('edit'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('qrCode'))
            ->defaultSort('sort_order')
            ->recordTitleAttribute('name')
            ->modelLabel(__('panels.app.tables.singular'))
            ->pluralModelLabel(__('panels.app.tables.plural'))
            ->description(fn (): ?string => $this->usageDescription())
            ->emptyStateHeading(__('panels.app.tables.empty'))
            ->emptyStateDescription(__('panels.app.tables.empty_help'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('panels.app.tables.fields.name'))
                    ->searchable(),
                TextColumn::make('qrCode.code')
                    ->label(__('panels.app.tables.fields.code'))
                    ->placeholder(__('panels.app.tables.fields.code_missing'))
                    ->fontFamily('mono'),
                IconColumn::make('qrCode.is_active')
                    ->label(__('panels.app.tables.fields.code_active'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('panels.app.tables.actions.create'))
                    ->authorize(fn (): bool => $this->canChange())
                    ->using(fn (array $data): LocationTable => ServiceCall::run(
                        fn () => app(LocationTableService::class)->create($this->location(), $data),
                        statePath: null,
                    )),
                $this->bulkCreateAction(),
                $this->downloadAllAction(),
            ])
            ->recordActions([
                Action::make('openCode')
                    ->label(__('panels.app.tables.actions.open_code'))
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('gray')
                    ->visible(fn (LocationTable $record): bool => $record->qrCode !== null && $this->userCan('view', $record->qrCode))
                    ->url(fn (LocationTable $record): string => QrCodeResource::getUrl('edit', ['record' => $record->qrCode])),
                Action::make('createCode')
                    ->label(__('panels.app.tables.actions.create_code'))
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('gray')
                    ->visible(fn (LocationTable $record): bool => $record->qrCode === null)
                    ->authorize(fn (): bool => $this->canChange())
                    ->action(function (LocationTable $record): void {
                        ServiceCall::run(fn () => app(LocationTableService::class)->createCode($record), statePath: null);
                        Notification::make()->success()->title(__('panels.app.tables.notifications.code_created'))->send();
                    }),
                EditAction::make()
                    ->authorize(fn (): bool => $this->canChange())
                    ->using(fn (LocationTable $record, array $data): LocationTable => ServiceCall::run(
                        fn () => app(LocationTableService::class)->update($record, $data),
                        statePath: null,
                    )),
                DeleteAction::make()
                    ->authorize(fn (): bool => $this->canDeleteTables())
                    ->modalDescription(__('panels.app.tables.confirm.delete'))
                    ->using(function (LocationTable $record): bool {
                        app(LocationTableService::class)->delete($record);

                        return true;
                    }),
            ]);
    }

    private function bulkCreateAction(): Action
    {
        return Action::make('bulkCreate')
            ->label(__('panels.app.tables.actions.bulk_create'))
            ->icon(Heroicon::OutlinedSquaresPlus)
            ->color('gray')
            ->authorize(fn (): bool => $this->canChange())
            ->modalHeading(__('panels.app.tables.actions.bulk_create'))
            ->modalSubmitActionLabel(__('panels.app.tables.actions.bulk_create_submit'))
            ->modalWidth('md')
            ->schema([
                TextInput::make('prefix')
                    ->label(__('panels.app.tables.fields.prefix'))
                    ->helperText(__('panels.app.tables.fields.prefix_help'))
                    ->default(__('panels.app.tables.default_prefix'))
                    ->maxLength(LocationTableService::MAX_NAME_LENGTH - 5),
                TextInput::make('start')
                    ->label(__('panels.app.tables.fields.start'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(9999)
                    ->required()
                    ->default(fn (): int => $this->location()->tables()->count() + 1),
                TextInput::make('count')
                    ->label(__('panels.app.tables.fields.count'))
                    ->helperText(__('panels.app.tables.fields.count_help', ['max' => LocationTableService::MAX_BULK]))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(LocationTableService::MAX_BULK)
                    ->required()
                    ->default(10),
            ])
            ->action(function (array $data): void {
                $tables = ServiceCall::run(
                    fn () => app(LocationTableService::class)->createMany($this->location(), $data['prefix'] ?? '', $data['start'] ?? null, $data['count'] ?? null),
                    statePath: null,
                );

                Notification::make()->success()->title(__('panels.app.tables.notifications.created', ['count' => $tables->count()]))->send();
            });
    }

    /**
     * Every table code of the branch in one ZIP, plain or on a printable
     * background. Part of viewing, like the single downloads.
     */
    private function downloadAllAction(): Action
    {
        return Action::make('downloadAll')
            ->label(__('panels.app.tables.actions.download_all'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->authorize(fn (): bool => $this->userCan('view', $this->location()))
            ->modalHeading(__('panels.app.tables.actions.download_all'))
            ->modalDescription(__('panels.app.tables.download_modal.description'))
            ->modalSubmitActionLabel(__('panels.app.tables.actions.download_all_submit'))
            ->modalWidth('md')
            ->schema([
                Select::make('background')
                    ->label(__('panels.app.tables.download_modal.format'))
                    ->options(['' => __('panels.app.tables.download_modal.plain')] + QrBackground::options())
                    ->default('')
                    ->selectablePlaceholder(false),
            ])
            ->action(function (array $data): ?StreamedResponse {
                // Unknown keys fail closed to the plain PNG.
                $background = QrBackground::tryFrom((string) ($data['background'] ?? ''));
                $codes = QrCode::query()
                    ->where('location_id', $this->location()->getKey())
                    ->whereNotNull('location_table_id')
                    ->with('table')
                    ->get()
                    ->sortBy(fn (QrCode $code): array => [$code->table?->sort_order, $code->table?->id])
                    ->values();

                if ($codes->isEmpty()) {
                    Notification::make()->warning()->title(__('panels.app.tables.notifications.nothing_to_download'))->send();

                    return null;
                }

                $content = $this->zip($codes->all(), $background);
                $slug = $this->location()->slug;

                return response()->streamDownload(
                    function () use ($content): void {
                        echo $content;
                    },
                    Str::slug(__('panels.app.tables.plural')).'-qr-'.$slug.'.zip',
                    ['Content-Type' => 'application/zip'],
                );
            });
    }

    /**
     * @param  list<QrCode>  $codes
     */
    private function zip(array $codes, ?QrBackground $background): string
    {
        $images = app(QrCodeImage::class);
        $path = tempnam(sys_get_temp_dir(), 'qr-tables-');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The table QR archive could not be created.');
        }

        try {
            foreach ($codes as $code) {
                $zip->addFromString(
                    $images->filename($code, 'png', $background),
                    $background === null ? $images->png($code) : $images->pngOnBackground($code, $background),
                );
            }

            $zip->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    private function usageDescription(): ?string
    {
        if (! $this->canChange()) {
            return null;
        }

        $gate = app(FeatureGate::class);
        $tenant = app(TenantContext::class)->tenant();
        $limit = $gate->limit($tenant, PlanFeature::MaxTables);

        return __('panels.app.tables.usage', [
            'usage' => $limit === null
                ? __('plans.unlimited')
                : __('plans.usage', ['used' => $gate->usage($tenant, PlanFeature::MaxTables), 'limit' => $limit]),
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
        return $this->canDeleteTables();
    }

    private function canChange(): bool
    {
        return ! $this->isReadOnly() && $this->userCan('update', $this->location());
    }

    private function canDeleteTables(): bool
    {
        return ! $this->isReadOnly() && $this->userCan('deleteTable', $this->location());
    }

    private function userCan(string $ability, Model $model): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can($ability, $model);
    }

    private function location(): Location
    {
        /** @var Location */
        return $this->getOwnerRecord();
    }
}
