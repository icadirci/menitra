<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuTheme;
use App\Enums\TenantStatus;
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
    use HasFactory, HasTranslations, HasUlids, SoftDeletes;

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

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active && ! $this->trashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TenantStatus::Active);
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
