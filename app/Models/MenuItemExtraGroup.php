<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Item ↔ extra group link (pivot model, so cache observers fire on attach/detach).
 */
class MenuItemExtraGroup extends Pivot
{
    use BelongsToTenant;

    protected $table = 'menu_item_extra_group';

    public $incrementing = true;
}
