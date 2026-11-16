<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdminAuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only admin audit log (written only by App\Services\Admin\AdminAudit).
 * Updates and deletes are refused here and by a database trigger.
 */
class AdminAuditEvent extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'action' => AdminAuditAction::class,
            'meta' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Admin audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Admin audit events are append-only.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }
}
