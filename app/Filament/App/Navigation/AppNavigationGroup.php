<?php

declare(strict_types=1);

namespace App\Filament\App\Navigation;

use Filament\Support\Contracts\HasLabel;

/**
 * Sidebar groups of the business panel, in display order. Filament hides a
 * group until a page or resource that the user may access belongs to it.
 */
enum AppNavigationGroup implements HasLabel
{
    case MenuManagement;
    case Locations;
    case Business;

    public function getLabel(): string
    {
        return match ($this) {
            self::MenuManagement => __('panels.app.navigation.menu_management'),
            self::Locations => __('panels.app.navigation.locations'),
            self::Business => __('panels.app.navigation.business'),
        };
    }
}
