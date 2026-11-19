<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaType;
use App\Enums\MenuTheme;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTenantMedia;
use App\Models\Concerns\HasTranslations;
use App\Models\Translations\LocationTranslation;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A branch of a business. Tenant-owned: always read through TenantScope.
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use BelongsToTenant, HasFactory, HasTenantMedia, HasTranslations, SoftDeletes;

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
            // tenant_id is set by the LocationMenu pivot model (BelongsToTenant), so eager loading works.
            ->using(LocationMenu::class)
            ->withPivot(['id', 'is_active', 'sort_order'])
            ->withTimestamps();
    }

    public function locationMenus(): HasMany
    {
        return $this->hasMany(LocationMenu::class);
    }

    public function itemStates(): HasMany
    {
        return $this->hasMany(LocationItemState::class);
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    /**
     * The branch's default QR code: its oldest active branch code
     * (registration creates the first one with the default branch). Table
     * codes never count: they identify one table.
     */
    public function defaultQrCode(): HasOne
    {
        return $this->hasOne(QrCode::class)->ofMany(
            ['id' => 'min'],
            fn ($query) => $query->where('is_active', true)->whereNull('location_table_id'),
        );
    }

    public function tables(): HasMany
    {
        return $this->hasMany(LocationTable::class)->orderBy('sort_order')->orderBy('id');
    }

    public function openingHours(): HasMany
    {
        return $this->hasMany(LocationOpeningHour::class)->orderBy('day_of_week')->orderBy('opens_at');
    }

    public function specialHours(): HasMany
    {
        return $this->hasMany(LocationSpecialHour::class)->orderBy('date')->orderBy('opens_at');
    }

    public function mediaAttributes(): array
    {
        return ['cover_path' => MediaType::Location];
    }

    public function mediaTenant(): Tenant
    {
        return $this->tenant ?? Tenant::withTrashed()->findOrFail($this->tenant_id);
    }

    protected function translationModel(): string
    {
        return LocationTranslation::class;
    }
}
