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
    case MaxMembers = 'max_members';
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

    /**
     * Whether the feature is built. Unbuilt features (custom domains, QR scan
     * analytics) can't be switched on in a plan (PlanService stores 0) and are
     * never advertised on the pricing page, so nothing is sold that doesn't
     * exist. Flip a case here when its implementation ships.
     */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::CustomDomain, self::QrAnalytics => false,
            default => true,
        };
    }

    public function label(): string
    {
        return __("plans.features.{$this->value}");
    }
}
