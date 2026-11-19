<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\MenuFontPair;
use App\Enums\MenuTheme;
use App\Filament\App\Navigation\AppNavigationGroup;
use App\Filament\App\Support\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Themes\ThemeService;
use App\Services\WaiterCalls\WaiterCallService;
use App\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use UnitEnum;

/**
 * Theme & appearance: the tenant's theme and the settings of the selected
 * theme (colours, font pair, layout options). Switching the theme in the
 * form loads that theme's saved settings, so earlier settings come back.
 * Themes without views are listed as "coming soon" and can't be chosen;
 * premium themes need the plan feature (ThemeService enforces both).
 * The tenant-wide waiter call switch lives here too (table service section,
 * written by WaiterCallService, which enforces the waiter_call feature).
 * Owner-only (TenantPolicy::manageSettings), checked on mount, on every
 * Livewire request and again in every action.
 */
class ManageTheme extends Page
{
    protected static string|UnitEnum|null $navigationGroup = AppNavigationGroup::Business;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'theme';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

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
        return __('panels.app.theme.navigation');
    }

    public function getTitle(): string
    {
        return __('panels.app.theme.title');
    }

    public function mount(): void
    {
        $theme = $this->tenant()->theme;

        $this->form->fill([
            'theme' => $theme->value,
            'settings' => $this->themes()->settingsFor($theme),
            'waiter_calls_enabled' => $this->tenant()->waiter_calls_enabled,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Callout::make(__('panels.app.theme.premium_lost.title'))
                    ->description(__('panels.app.theme.premium_lost.body'))
                    ->warning()
                    ->columnSpanFull()
                    ->visible(fn (): bool => ! $this->themes()->planAllows($this->tenant(), $this->tenant()->theme)),
                Section::make(__('panels.app.theme.sections.theme'))
                    ->description(__('panels.app.theme.sections.theme_help'))
                    ->columnSpanFull()
                    ->schema([
                        Radio::make('theme')
                            ->hiddenLabel()
                            ->required()
                            ->live()
                            ->options(collect(MenuTheme::cases())->mapWithKeys(fn (MenuTheme $theme): array => [$theme->value => $theme->label()])->all())
                            ->descriptions(fn (): array => collect(MenuTheme::cases())->mapWithKeys(fn (MenuTheme $theme): array => [$theme->value => $this->themeDescription($theme)])->all())
                            ->disableOptionWhen(fn (string $value): bool => ! $this->selectable(MenuTheme::from($value)))
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (($theme = MenuTheme::tryFrom((string) $state)) !== null) {
                                    $set('settings', $this->themes()->settingsFor($theme));
                                }
                            }),
                    ]),
                Section::make(__('panels.app.theme.sections.colors'))
                    ->description(__('panels.app.theme.sections.colors_help'))
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->schema(array_map(
                        fn (string $name): ColorPicker => ColorPicker::make("settings.colors.{$name}")
                            ->label(__("panels.app.theme.colors.{$name}"))
                            ->hex()
                            ->required()
                            ->regex('/^#[0-9A-Fa-f]{6}$/'),
                        array_keys(MenuTheme::Classic->defaults()['colors']),
                    )),
                Section::make(__('panels.app.theme.sections.typography'))
                    ->columnSpanFull()
                    ->schema([
                        Select::make('settings.font_pair')
                            ->label(__('panels.app.theme.fields.font_pair'))
                            ->options(fn (Get $get): array => collect($this->selectedTheme($get)->fontPairs())
                                ->mapWithKeys(fn (MenuFontPair $pair): array => [$pair->value => $pair->label()])
                                ->all())
                            ->required()
                            ->selectablePlaceholder(false),
                    ]),
                Section::make(__('panels.app.theme.sections.layout'))
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'sm' => 3])
                    ->visible(fn (Get $get): bool => $this->selectedTheme($get)->defaults()['layout'] !== [])
                    ->schema($this->layoutFields()),
                $this->tableServiceSection(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('panels.app.actions.save'))
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                        Action::make('resetDefaults')
                            ->label(__('panels.app.theme.actions.reset'))
                            ->color('gray')
                            ->requiresConfirmation()
                            ->modalDescription(__('panels.app.theme.confirm.reset'))
                            ->action(fn () => $this->resetDefaults()),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $theme = MenuTheme::tryFrom((string) ($data['theme'] ?? ''));
        abort_if($theme === null, 422);

        $tenant = $this->tenant();
        // Absent while the switch is disabled (not in the plan and off): unchanged.
        $waiterCalls = (bool) ($data['waiter_calls_enabled'] ?? $tenant->waiter_calls_enabled);

        ServiceCall::run(fn () => DB::transaction(function () use ($tenant, $theme, $data, $waiterCalls): void {
            $this->themes()->save($tenant, $theme, (array) ($data['settings'] ?? []));
            $this->waiterCalls()->setEnabled($tenant, $waiterCalls);
        }));

        Notification::make()->success()->title(__('panels.app.theme.saved'))->send();
    }

    /**
     * Puts the selected theme's defaults into the form (saved with "Save").
     */
    public function resetDefaults(): void
    {
        abort_unless(static::canAccess(), 403);

        $theme = MenuTheme::tryFrom((string) ($this->data['theme'] ?? '')) ?? $this->tenant()->theme;
        $this->data['settings'] = $theme->defaults();
    }

    /**
     * Layout options of every theme; each is shown only while its theme is selected.
     *
     * @return list<Component|Toggle|Select>
     */
    private function layoutFields(): array
    {
        $fields = [];

        foreach (MenuTheme::cases() as $theme) {
            foreach ($theme->defaults()['layout'] as $name => $default) {
                if (isset($fields[$name])) {
                    continue;
                }

                $field = is_bool($default)
                    ? Toggle::make("settings.layout.{$name}")->inline(false)
                    : Select::make("settings.layout.{$name}")
                        ->options(fn (Get $get): array => collect($this->selectedTheme($get)->layoutChoices()[$name] ?? [])
                            ->mapWithKeys(fn (string $choice): array => [$choice => __("panels.app.theme.layout_choices.{$name}.{$choice}")])
                            ->all())
                        ->selectablePlaceholder(false);

                $fields[$name] = $field
                    ->label(__("panels.app.theme.layout.{$name}"))
                    ->helperText(Lang::has("panels.app.theme.layout_help.{$name}") ? __("panels.app.theme.layout_help.{$name}") : null)
                    ->visible(fn (Get $get): bool => array_key_exists($name, $this->selectedTheme($get)->defaults()['layout']));
            }
        }

        return array_values($fields);
    }

    /**
     * Waiter call switch. Without the plan feature it can only be turned
     * off; WaiterCallService refuses turning it on whatever the form sends.
     */
    private function tableServiceSection(): Section
    {
        return Section::make(__('panels.app.theme.waiter_calls.section'))
            ->description(__('panels.app.theme.waiter_calls.section_help'))
            ->columnSpanFull()
            ->schema([
                Callout::make(__('panels.app.theme.waiter_calls.plan_lost'))
                    ->warning()
                    ->visible(fn (): bool => $this->tenant()->waiter_calls_enabled && ! $this->waiterCalls()->planAllows($this->tenant())),
                Toggle::make('waiter_calls_enabled')
                    ->label(__('panels.app.theme.waiter_calls.enabled'))
                    ->helperText(fn (): string => $this->waiterCalls()->planAllows($this->tenant())
                        ? __('panels.app.theme.waiter_calls.enabled_help')
                        : __('panels.app.theme.waiter_calls.not_in_plan'))
                    ->disabled(fn (): bool => ! $this->tenant()->waiter_calls_enabled && ! $this->waiterCalls()->planAllows($this->tenant())),
            ]);
    }

    private function waiterCalls(): WaiterCallService
    {
        return app(WaiterCallService::class);
    }

    private function selectable(MenuTheme $theme): bool
    {
        return $theme->isBuilt()
            && ($theme === $this->tenant()->theme || $this->themes()->planAllows($this->tenant(), $theme));
    }

    private function themeDescription(MenuTheme $theme): string
    {
        $description = __("panels.app.theme.themes.{$theme->value}.description");

        return match (true) {
            ! $theme->isBuilt() => $description.' · '.__('panels.app.theme.coming_soon'),
            ! $this->selectable($theme) => $description.' · '.__('panels.app.theme.not_in_plan'),
            default => $description,
        };
    }

    private function selectedTheme(Get $get): MenuTheme
    {
        return MenuTheme::tryFrom((string) $get('theme')) ?? $this->tenant()->theme;
    }

    private function themes(): ThemeService
    {
        return app(ThemeService::class);
    }

    private function tenant(): Tenant
    {
        return app(TenantContext::class)->tenant();
    }
}
