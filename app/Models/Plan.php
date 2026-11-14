<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Translations\PlanTranslation;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform-level plan (not tenant-owned).
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'is_active',
        'is_public',
        'trial_days',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'trial_days' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function trialDays(): int
    {
        return $this->trial_days ?? (int) config('subscriptions.trial_days');
    }

    protected function translationModel(): string
    {
        return PlanTranslation::class;
    }

    protected function translationFallbackLocale(): ?string
    {
        return config('locales.default');
    }
}
