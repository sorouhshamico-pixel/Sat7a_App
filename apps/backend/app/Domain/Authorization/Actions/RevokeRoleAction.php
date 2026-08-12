<?php

namespace App\Domain\Authorization\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Authorization\Models\Role;
use App\Models\User;

class RevokeRoleAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(User $target, Role $role, User $actor): void
    {
        $target->roles()->detach($role->id);
        $target->forgetCachedPermissions();

        $this->auditLogger->log(
            actor: $actor,
            action: 'role.revoked',
            entityType: 'user',
            entityId: $target->public_id,
            oldValues: ['role' => $role->name],
        );
    }
}
