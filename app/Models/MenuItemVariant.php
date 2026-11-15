<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use App\Models\Translations\MenuItemVariantTranslation;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Size or portion of an item with its own price (kuruş). At most one default.
 */
class MenuItemVariant extends Model
{
    use BelongsToTenant, HasTranslations, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'price',
        'is_default',
        'is_available',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_default' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    protected function translationModel(): string
    {
        return MenuItemVariantTranslation::class;
    }
}
