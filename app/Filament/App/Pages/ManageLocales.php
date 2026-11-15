<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\PlanFeature;
use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\TenantLocaleException;
use App\Filament\App\Navigation\AppNavigationGroup;
use App\Models\Tenant;
use App\Models\TenantLocale;
use App\Models\User;
use App\Services\Subscription\FeatureGate;
use App\Services\Tenancy\TenantLocaleService;
use App\Tenancy\TenantContext;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Languages of the business (tenant_locales + tenants.default_locale).
 *
 * Presentation only: every change goes through TenantLocaleService, which
 * enforces the invariants and the plan limit. Owner-only (TenantPolicy),
 * checked on mount, on every Livewire request and again in each action.
 */
class ManageLocales extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = AppNavigationGroup::Business;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'languages';

    public static function canAccess(): bool
    {
        $context = app(TenantContext::class);
        $user = auth()->user();

        return $context->check()
            && $user instanceof User
            && $user->can('manageSettings', $context->tenant());
    }

    public static function getNavigationLabel(): string
    {
        return __('panels.app.locales.navigation');
    }

    public function getTitle(): string
    {
        return __('panels.app.locales.title');
    }

    public function getSubheading(): ?string
    {
        $tenant = $this->tenant();
        $limit = app(FeatureGate::class)->limit($tenant, PlanFeature::MaxLanguages);
        $used = app(FeatureGate::class)->usage($tenant, PlanFeature::MaxLanguages);

        return __('panels.app.locales.subheading', [
            'usage' => $limit === null
                ? __('plans.unlimited')
                : __('plans.usage', ['used' => $used, 'limit' => $limit]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(TenantLocale::query())
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated(false)
            ->columns([
                TextColumn::make('locale')
                    ->label(__('panels.app.locales.columns.language'))
                    ->formatStateUsing(fn (TenantLocale $record): string => $record->nativeName()),
                TextColumn::make('code')
                    ->label(__('panels.app.locales.columns.code'))
                    ->state(fn (TenantLocale $record): string => $record->locale)
                    ->badge()
                    ->color('gray'),
                TextColumn::make('is_default')
                    ->label('')
                    ->state(fn (TenantLocale $record): ?string => $this->isDefault($record) ? __('panels.app.translations.default') : null)
                    ->badge()
                    ->color('success'),
            ])
            ->headerActions([$this->addAction()])
            ->recordActions([$this->makeDefaultAction(), $this->disableAction()]);
    }

    /**
     * Drag-and-drop ordering goes through the service instead of writing
     * sort_order directly. Record keys come from the client: they are resolved
     * through the tenant-scoped query, and the service requires exactly the
     * enabled set.
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        abort_unless(static::canAccess(), 403);

        $locales = TenantLocale::query()->whereKey($order)->pluck('locale', 'id');
        $ordered = [];

        foreach ($order as $key) {
            if (isset($locales[(int) $key])) {
                $ordered[] = $locales[(int) $key];
            }
        }

        $this->runService(fn (TenantLocaleService $service) => $service->reorder($ordered));
    }

    private function addAction(): Action
    {
        return Action::make('addLocale')
            ->label(__('panels.app.locales.actions.add'))
            ->icon(Heroicon::OutlinedPlus)
            ->authorize(fn (): bool => static::canAccess())
            ->disabled(fn (): bool => $this->availableLocales() === []
                || ! app(FeatureGate::class)->allows($this->tenant(), PlanFeature::MaxLanguages))
            ->tooltip(fn (): ?string => app(FeatureGate::class)->allows($this->tenant(), PlanFeature::MaxLanguages)
                ? null
                : __('panels.app.locales.limit_reached'))
            ->schema([
                Select::make('locale')
                    ->label(__('panels.app.locales.columns.language'))
                    ->options(fn (): array => $this->availableLocales())
                    ->required(),
            ])
            ->action(fn (array $data, Action $action) => $this->runService(
                fn (TenantLocaleService $service) => $service->enable((string) $data['locale']),
                __('panels.app.locales.notifications.added'),
                $action,
            ));
    }

    private function makeDefaultAction(): Action
    {
        return Action::make('makeDefault')
            ->label(__('panels.app.locales.actions.make_default'))
            ->icon(Heroicon::OutlinedStar)
            ->authorize(fn (): bool => static::canAccess())
            ->visible(fn (TenantLocale $record): bool => ! $this->isDefault($record))
            ->requiresConfirmation()
            ->modalDescription(__('panels.app.locales.confirm.make_default'))
            ->action(fn (TenantLocale $record, Action $action) => $this->runService(
                fn (TenantLocaleService $service) => $service->setDefault($record->locale),
                __('panels.app.locales.notifications.default_changed'),
                $action,
            ));
    }

    private function disableAction(): Action
    {
        return Action::make('disable')
            ->label(__('panels.app.locales.actions.remove'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->authorize(fn (): bool => static::canAccess())
            ->visible(fn (TenantLocale $record): bool => ! $this->isDefault($record))
            ->requiresConfirmation()
            ->modalDescription(__('panels.app.locales.confirm.remove'))
            ->action(fn (TenantLocale $record, Action $action) => $this->runService(
                fn (TenantLocaleService $service) => $service->disable($record->locale),
                __('panels.app.locales.notifications.removed'),
                $action,
            ));
    }

    /**
     * Runs a service call and turns rule violations into a notification.
     *
     * @param  Closure(TenantLocaleService): mixed  $callback
     */
    private function runService(Closure $callback, ?string $success = null, ?Action $action = null): void
    {
        try {
            $callback(app(TenantLocaleService::class));
        } catch (TenantLocaleException|PlanLimitExceededException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            $action?->halt();

            return;
        }

        if ($success !== null) {
            Notification::make()->success()->title($success)->send();
        }
    }

    /**
     * @return array<string, string> platform locales not enabled yet
     */
    private function availableLocales(): array
    {
        $enabled = TenantLocale::query()->pluck('locale')->all();

        return array_diff_key((array) config('locales.supported'), array_flip($enabled));
    }

    private function isDefault(TenantLocale $record): bool
    {
        return $record->locale === $this->tenant()->default_locale;
    }

    private function tenant(): Tenant
    {
        return app(TenantContext::class)->tenant();
    }
}
