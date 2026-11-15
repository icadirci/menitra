<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DietaryLabel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemDietaryLabel extends Model
{
    use BelongsToTenant;

    protected $table = 'menu_item_dietary_labels';

    /** @var list<string> */
    protected $fillable = ['label'];

    protected function casts(): array
    {
        return ['label' => DietaryLabel::class];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }
}
