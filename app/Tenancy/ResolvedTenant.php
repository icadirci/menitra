<?php

declare(strict_types=1);

namespace App\Tenancy;

use Carbon\CarbonImmutable;

/**
 * Cached result of public tenant resolution (qrmenu:host:* / qrmenu:slug:*).
 * Plain values only. `accessUntil` is compared with the clock on every
 * request, so expiry takes effect while the entry is still cached.
 */
final readonly class ResolvedTenant
{
    /**
     * @param  list<string>  $locales  enabled locales, default first
     * @param  array<string, string>  $names  locale => business name
     * @param  array<string, array<string, mixed>>  $themeSettings  theme key => saved theme_settings.settings (validated again by ThemeSettings::fromStored())
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $status,
        public string $defaultLocale,
        public array $locales,
        public string $theme,
        public ?int $accessUntil,
        public array $names,
        public bool $premiumThemes,
        public bool $removeBranding,
        public array $themeSettings = [],
    ) {}

    public function isOnline(?int $now = null): bool
    {
        return $this->status === 'active'
            && $this->accessUntil !== null
            && ($now ?? CarbonImmutable::now()->getTimestamp()) <= $this->accessUntil;
    }

    public function name(string $locale): string
    {
        return $this->names[$locale] ?? $this->names[$this->defaultLocale] ?? (reset($this->names) ?: $this->slug);
    }
}
