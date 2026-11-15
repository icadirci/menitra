<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Filament\App\Pages\EditBusinessProfile;
use App\Filament\App\Pages\ManageTheme;
use App\Filament\App\Resources\Locations\LocationResource;
use App\Filament\App\Resources\MenuItems\MenuItemResource;
use App\Filament\App\Resources\Menus\MenuResource;
use App\Filament\App\Resources\QrCodes\QrCodeResource;
use App\Filament\App\Support\PanelContext;
use App\Services\Dashboard\BusinessDashboard;
use App\Services\Dashboard\OnboardingStep;
use App\Tenancy\TenantContext;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * "Get your menu online" checklist. Each step is derived from stored data
 * (BusinessDashboard); nothing is stored, and the widget disappears once
 * every step is done. Owner-only: the steps are tenant-wide settings.
 */
class OnboardingChecklistWidget extends Widget implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.app.widgets.schema-widget';

    public static function canView(): bool
    {
        $context = app(TenantContext::class);

        return $context->check()
            && (PanelContext::user()?->can('manageSettings', $context->tenant()) ?? false)
            && ! app(BusinessDashboard::class)->isOnboardingComplete();
    }

    public function content(Schema $schema): Schema
    {
        $steps = app(BusinessDashboard::class)->steps();
        $done = count(array_filter($steps, fn (OnboardingStep $step): bool => $step->done));

        return $schema->components([
            Section::make(__('panels.app.dashboard.onboarding.heading'))
                ->description(__('panels.app.dashboard.onboarding.description', ['done' => $done, 'total' => count($steps)]))
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->columns(['default' => 1, 'md' => 2])
                ->schema(array_map(fn (OnboardingStep $step): TextEntry => $this->entry($step), $steps)),
        ]);
    }

    private function entry(OnboardingStep $step): TextEntry
    {
        return TextEntry::make("step_{$step->key}")
            ->label(__("panels.app.dashboard.onboarding.steps.{$step->key}.title"))
            ->state($step->done
                ? __('panels.app.dashboard.onboarding.done')
                : __("panels.app.dashboard.onboarding.steps.{$step->key}.description"))
            ->icon($step->done ? Heroicon::CheckCircle : Heroicon::OutlinedArrowRightCircle)
            ->iconColor($step->done ? 'success' : 'primary')
            ->color($step->done ? 'gray' : null)
            ->url($this->url($step->key));
    }

    private function url(string $key): string
    {
        $defaultLocation = app(BusinessDashboard::class)->defaultLocationId();

        return match ($key) {
            OnboardingStep::PROFILE => EditBusinessProfile::getUrl(),
            OnboardingStep::ITEMS => MenuItemResource::getUrl(),
            OnboardingStep::PUBLISH => MenuResource::getUrl(),
            OnboardingStep::HOURS => $defaultLocation === null
                ? LocationResource::getUrl()
                : LocationResource::getUrl('edit', ['record' => $defaultLocation]),
            OnboardingStep::THEME => ManageTheme::getUrl(),
            OnboardingStep::QR => QrCodeResource::getUrl(),
        };
    }
}
