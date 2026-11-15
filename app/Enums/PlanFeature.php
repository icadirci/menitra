<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Plan-limited features (plan_limits.feature). Count features limit how many
 * rows a tenant may have; boolean features are on (1) or off (0).
 */
enum PlanFeature: string
{
    case MaxLocations = 'max_locations';
    case MaxMenus = 'max_menus';
    case MaxItems = 'max_items';
    case MaxLanguages = 'max_languages';
    case MaxQrCodes = 'max_qr_codes';
    case PremiumThemes = 'premium_themes';
    case CustomDomain = 'custom_domain';
    case RemoveBranding = 'remove_branding';
    case QrAnalytics = 'qr_analytics';

    public function isBoolean(): bool
    {
        return match ($this) {
            self::PremiumThemes, self::CustomDomain, self::RemoveBranding, self::QrAnalytics => true,
            default => false,
        };
    }

    public function label(): string
    {
        return __("plans.features.{$this->value}");
    }
}
