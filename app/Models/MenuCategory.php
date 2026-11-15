<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTenantMedia;
use App\Models\Concerns\HasTranslations;
use App\Models\Translations\MenuCategoryTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A category of a menu. Written through MenuContentService.
 */
class MenuCategory extends Model
{
    use BelongsToTenant, HasTenantMedia, HasTranslations;

    /** @var list<string> */
    protected $fillable = [
        'is_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function mediaAttributes(): array
    {
        return ['image_path' => MediaType::Category];
    }

    public function mediaTenant(): Tenant
    {
        return Tenant::withTrashed()->findOrFail($this->tenant_id);
    }

    protected function translationModel(): string
    {
        return MenuCategoryTranslation::class;
    }
}
