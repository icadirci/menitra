<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuTheme;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Saved settings of one theme for one tenant (unique per tenant and theme).
 * Written only through App\Services\Themes\ThemeService, which validates
 * `settings` with MenuTheme::validator().
 */
class ThemeSetting extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'theme',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'theme' => MenuTheme::class,
            'settings' => 'array',
        ];
    }
}
