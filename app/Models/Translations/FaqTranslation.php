<?php

declare(strict_types=1);

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class FaqTranslation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'locale',
        'question',
        'answer',
    ];
}
