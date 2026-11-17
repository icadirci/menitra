<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingProfileType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-owned invoice details (one row per tenant), written only by
 * BillingProfileService. tax_id (TCKN or VKN) is encrypted and hidden from
 * serialization.
 */
class TenantBillingProfile extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'type',
        'name',
        'tax_office',
        'tax_id',
        'email',
        'phone',
        'address',
        'city',
        'district',
        'postal_code',
    ];

    /** @var list<string> */
    protected $hidden = ['tax_id'];

    protected function casts(): array
    {
        return [
            'type' => BillingProfileType::class,
            'tax_id' => 'encrypted',
        ];
    }

    /**
     * The snapshot stored on a payment (receipt) and sent to the provider.
     *
     * @return array<string, string|null>
     */
    public function snapshot(): array
    {
        return [
            'type' => $this->type->value,
            'name' => $this->name,
            'tax_office' => $this->tax_office,
            'tax_id' => $this->tax_id,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'district' => $this->district,
            'postal_code' => $this->postal_code,
        ];
    }
}
