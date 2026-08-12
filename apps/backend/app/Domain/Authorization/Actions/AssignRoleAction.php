<?php

namespace App\Domain\Authorization\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\Models\Role;
use App\Models\User;

/**
 * Role changes are audited without exception, including when a super admin
 * performs them (see docs/SECURITY.md §Audit).
 */
class AssignRoleAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(User $target, Role $role, User $actor): void
    {
        $target->roles()->syncWithoutDetaching([
            $role->id => ['assigned_by' => $actor->id],
        ]);

        $target->forgetCachedPermissions();

        $this->auditLogger->log(
            actor: $actor,
            action: 'role.assigned',
            entityType: 'user',
            entityId: $target->public_id,
            newValues: ['role' => $role->name],
        );
    }
}
