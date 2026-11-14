<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuTheme;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use App\Models\Translations\LocationTranslation;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A branch of a business. Tenant-owned: always read through TenantScope.
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use BelongsToTenant, HasFactory, HasTranslations, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'is_active',
        'theme',
        'phone',
        'email',
        'whatsapp',
        'address_line',
        'district',
        'city',
        'postal_code',
        'country_code',
        'latitude',
        'longitude',
        'timezone',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'theme' => MenuTheme::class,
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
        ];
    }

    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(TenantMembership::class, 'location_user', 'location_id', 'tenant_user_id')
            ->withPivotValue('tenant_id', $this->tenant_id)
            ->withTimestamps();
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'location_menu')
            ->withPivotValue('tenant_id', $this->tenant_id)
            ->withPivot(['is_active', 'sort_order'])
            ->withTimestamps();
    }

    protected function translationModel(): string
    {
        return LocationTranslation::class;
    }
}
