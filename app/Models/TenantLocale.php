<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A locale enabled for a tenant. The default locale is tenants.default_locale;
 * changes go through App\Services\Tenancy\TenantLocaleService.
 */
class TenantLocale extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'locale',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Native name from config/locales.php.
     */
    public function nativeName(): string
    {
        return config("locales.supported.{$this->locale}", $this->locale);
    }
}
