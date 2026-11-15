<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Allergen;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemAllergen extends Model
{
    use BelongsToTenant;

    protected $table = 'menu_item_allergens';

    /** @var list<string> */
    protected $fillable = ['allergen'];

    protected function casts(): array
    {
        return ['allergen' => Allergen::class];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }
}
