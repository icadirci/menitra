<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantRole;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Team invitation (launch plan Faz 7). Written only by TeamInvitationService;
 * nothing is mass-assignable. The raw token is never stored, only its
 * SHA-256 hash (token_hash, hidden from serialization).
 *
 * Open = neither accepted nor revoked; pending = open and not expired.
 * Pending invitations count against max_members.
 */
class TenantInvitation extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'role' => TenantRole::class,
            'expires_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /**
     * Invited branches. The pivot carries this invitation's tenant_id, and the
     * composite FKs reject a location of any other tenant.
     */
    public function locations(): BelongsToMany
    {
        $relation = $this->belongsToMany(Location::class, 'tenant_invitation_location', 'tenant_invitation_id', 'location_id')->withTimestamps();

        // Eager loading builds the relation on a blank instance (no tenant_id);
        // the pivot value is needed only for writes from a loaded record.
        return $this->tenant_id === null ? $relation : $relation->withPivotValue('tenant_id', $this->tenant_id);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(TenantMembership::class, 'invited_by_membership_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->whereNull('revoked_at');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->open()->where('expires_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null;
    }

    public function isPending(): bool
    {
        return $this->isOpen() && $this->expires_at->isFuture();
    }

    public function status(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            default => 'pending',
        };
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
