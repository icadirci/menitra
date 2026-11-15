<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FaqPlacement;
use App\Models\Concerns\HasTranslations;
use App\Models\Translations\FaqTranslation;
use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform-level marketing FAQ entry (not tenant-owned), managed in /admin.
 */
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    protected $fillable = [
        'placement',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'placement' => FaqPlacement::class,
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    protected function translationModel(): string
    {
        return FaqTranslation::class;
    }

    protected function translationFallbackLocale(): ?string
    {
        return config('locales.default');
    }
}
