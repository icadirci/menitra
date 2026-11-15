<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaType;
use App\Enums\MenuTheme;
use App\Enums\TenantStatus;
use App\Models\Concerns\HasTenantMedia;
use App\Models\Concerns\HasTranslations;
use App\Models\Translations\TenantTranslation;
use Database\Factories\TenantFactory;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One tenant = one business. Its name and texts live in tenant_translations.
 */
class Tenant extends Model implements HasName
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasTenantMedia, HasTranslations, HasUlids, SoftDeletes;

    /**
     * `status` is not fillable: it changes only through platform actions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'currency',
        'default_locale',
        'theme',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'theme' => MenuTheme::class,
        ];
    }

    /**
     * Only `public_id` is a ULID; the primary key stays an incrementing bigint.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Enabled locales (tenant-scoped: read inside this tenant's context).
     */
    public function locales(): HasMany
    {
        return $this->hasMany(TenantLocale::class);
    }

    /**
     * Enabled locale codes, default first, then by sort order. The default is
     * always included (tenants.default_locale is the source of truth).
     *
     * @return list<string>
     */
    public function enabledLocales(): array
    {
        $locales = $this->locales()->orderBy('sort_order')->orderBy('id')->pluck('locale')->all();

        return array_values(array_unique([$this->default_locale, ...$locales]));
    }

    /**
     * Subscription history; at most one row has ended_at = null (live).
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active && ! $this->trashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TenantStatus::Active);
    }

    public function mediaAttributes(): array
    {
        return ['logo_path' => MediaType::Logo, 'cover_path' => MediaType::Cover];
    }

    public function mediaTenant(): Tenant
    {
        return $this;
    }

    public function getFilamentName(): string
    {
        return $this->translate()?->name ?? $this->slug;
    }

    protected function translationModel(): string
    {
        return TenantTranslation::class;
    }

    protected function translationFallbackLocale(): ?string
    {
        return $this->default_locale;
    }
}
