<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use App\Models\Translations\ExtraTranslation;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One option of an extra group; price_delta in kuruş (may be negative).
 */
class Extra extends Model
{
    use BelongsToTenant, HasTranslations, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'price_delta',
        'is_available',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'integer',
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(ExtraGroup::class, 'extra_group_id');
    }

    protected function translationModel(): string
    {
        return ExtraTranslation::class;
    }
}
