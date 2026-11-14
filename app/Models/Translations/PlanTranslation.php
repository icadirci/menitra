<?php

declare(strict_types=1);

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class PlanTranslation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'locale',
        'name',
        'description',
    ];
}
