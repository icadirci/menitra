<?php

declare(strict_types=1);

namespace App\Models\Translations;

use Illuminate\Database\Eloquent\Model;

class TestimonialTranslation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'locale',
        'quote',
        'author_role',
    ];
}
