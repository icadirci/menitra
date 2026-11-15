<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExtraSelectionType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use App\Models\Translations\ExtraGroupTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reusable group of extras (e.g. "Milk choice"), linked to items. Owner-only.
 */
class ExtraGroup extends Model
{
    use BelongsToTenant, HasTranslations;

    /** @var list<string> */
    protected $fillable = [
        'selection_type',
        'min_select',
        'max_select',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'selection_type' => ExtraSelectionType::class,
            'min_select' => 'integer',
            'max_select' => 'integer',
            'is_required' => 'boolean',
        ];
    }

    public function extras(): HasMany
    {
        return $this->hasMany(Extra::class)->orderBy('sort_order')->orderBy('id');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_extra_group')
            // tenant_id is set by the pivot model (BelongsToTenant), so eager loading works.
            ->using(MenuItemExtraGroup::class)
            ->withTimestamps();
    }

    protected function translationModel(): string
    {
        return ExtraGroupTranslation::class;
    }
}
