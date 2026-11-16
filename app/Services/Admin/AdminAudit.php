<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\AdminAuditAction;
use App\Models\AdminAuditEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Writes the append-only admin audit log. Meta holds identifiers and state
 * values only (slugs, roles, reasons), never secrets.
 */
final class AdminAudit
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(AdminAuditAction $action, User $actor, Tenant|User|null $subject = null, array $meta = []): AdminAuditEvent
    {
        $event = new AdminAuditEvent;
        $event->forceFill([
            'actor_user_id' => $actor->getKey(),
            'action' => $action,
            'subject_type' => match (true) {
                $subject instanceof Tenant => 'tenant',
                $subject instanceof User => 'user',
                $subject === null => null,
            },
            'subject_id' => $subject?->getKey(),
            'tenant_id' => $subject instanceof Tenant ? $subject->getKey() : null,
            'meta' => $meta === [] ? null : $meta,
            'ip' => $this->request->ip(),
        ]);

        if (! $event->save()) {
            throw new InvalidArgumentException('The admin audit event could not be written.');
        }

        return $event;
    }
}
