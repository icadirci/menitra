<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Translations live in a `{entity}_translations` table with one row per locale.
 * Fallback: requested locale → tenant default locale → first available.
 */
trait HasTranslations
{
    /**
     * @return class-string<Model>
     */
    abstract protected function translationModel(): string;

    public function translations(): HasMany
    {
        return $this->hasMany($this->translationModel());
    }

    public function translate(?string $locale = null): ?Model
    {
        $translations = $this->translations;
        $locale ??= app()->getLocale();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', $this->translationFallbackLocale())
            ?? $translations->first();
    }

    /**
     * @param  list<string>  $locales
     */
    public function scopeWithTranslations(Builder $query, array $locales): Builder
    {
        return $query->with(['translations' => fn (HasMany $relation) => $relation->whereIn('locale', $locales)]);
    }

    protected function translationFallbackLocale(): ?string
    {
        $context = app(TenantContext::class);

        return $context->check() ? $context->tenant()->default_locale : null;
    }
}
