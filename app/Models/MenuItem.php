<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTenantMedia;
use App\Models\Concerns\HasTranslations;
use App\Models\Translations\MenuItemTranslation;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A product. Price in kuruş (null = no price shown). Written through
 * MenuContentService; per-branch sold-out state lives in location_item_states.
 */
class MenuItem extends Model
{
    use BelongsToTenant, HasTenantMedia, HasTranslations, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'price',
        'is_visible',
        'is_available',
        'is_featured',
        'is_popular',
        'calories',
        'preparation_minutes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_visible' => 'boolean',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'is_popular' => 'boolean',
            'calories' => 'integer',
            'preparation_minutes' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function allergens(): HasMany
    {
        return $this->hasMany(MenuItemAllergen::class);
    }

    public function dietaryLabels(): HasMany
    {
        return $this->hasMany(MenuItemDietaryLabel::class);
    }

    public function extraGroups(): BelongsToMany
    {
        return $this->belongsToMany(ExtraGroup::class, 'menu_item_extra_group')
            // tenant_id is set by the pivot model (BelongsToTenant), so eager loading works.
            ->using(MenuItemExtraGroup::class)
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->withTimestamps();
    }

    public function locationStates(): HasMany
    {
        return $this->hasMany(LocationItemState::class);
    }

    public function mediaAttributes(): array
    {
        return ['image_path' => MediaType::Item];
    }

    public function mediaTenant(): Tenant
    {
        return Tenant::withTrashed()->findOrFail($this->tenant_id);
    }

    protected function translationModel(): string
    {
        return MenuItemTranslation::class;
    }
}
