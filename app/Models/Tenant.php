<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaType;
use App\Enums\MenuTheme;
use App\Enums\TenantStatus;
use App\Models\Concerns\HasTenantMedia;
use App\Models\Concerns\HasTranslations;
use App\Models\Translations\TenantTranslation;
use App\Support\Media\TenantMediaPath;
use Database\Factories\TenantFactory;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * One tenant = one business. Its name and texts live in tenant_translations.
 */
class Tenant extends Model implements HasAvatar, HasName
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
            'waiter_calls_enabled' => 'boolean',
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
    /**
     * Custom domains (reserved, later phase).
     */
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

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

    /**
     * Checkouts (iyzico); read through the tenant scope, or by /admin in its bypass area.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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

    /**
     * The logo in the panels' tenant switcher; without one Filament falls back
     * to the initials avatar (InitialsAvatarProvider).
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return filled($this->logo_path) ? Storage::disk(TenantMediaPath::DISK)->url($this->logo_path) : null;
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
