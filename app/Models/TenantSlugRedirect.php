<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A former slug of a tenant: public menu URLs with it answer 301 to the
 * current slug, and no other tenant can take it. Platform data (not
 * tenant-scoped, like tenants.slug); written only by TenantSlugService.
 */
class TenantSlugRedirect extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
