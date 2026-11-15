<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant's custom domain. Reserved for a later phase: there is no resolver
 * (DomainTenantResolver), no panel and no verification flow yet, and
 * MenuUrlGenerator ignores this table until custom domains are built.
 */
class TenantDomain extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'domain',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'immutable_datetime',
        ];
    }

    protected function setDomainAttribute(string $value): void
    {
        $this->attributes['domain'] = strtolower(trim($value));
    }
}
