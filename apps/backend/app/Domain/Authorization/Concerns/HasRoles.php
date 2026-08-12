<?php

namespace App\Domain\Authorization\Concerns;

use App\Domain\Authorization\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

trait HasRoles
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot(['assigned_at', 'assigned_by']);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->cachedPermissionSet()['roles']->contains($roleName);
    }

    public function hasPermission(string $permissionName): bool
    {
        return $this->cachedPermissionSet()['permissions']->contains($permissionName);
    }

    /**
     * Permission checks happen on nearly every authenticated request (via
     * Gate::before — see AuthorizationServiceProvider), so the resolved
     * role/permission names are cached briefly per-user rather than
     * re-joining role_user/permission_role on every single check within a
     * request.
     *
     * @return array{roles: Collection<int, string>, permissions: Collection<int, string>}
     */
    private function cachedPermissionSet(): array
    {
        return Cache::remember(
            "users:{$this->id}:permission-set",
            now()->addMinutes(5),
            function () {
                $roles = $this->roles()->with('permissions')->get();

                return [
                    'roles' => $roles->pluck('name'),
                    'permissions' => $roles->flatMap(fn (Role $role) => $role->permissions->pluck('name'))->unique(),
                ];
            },
        );
    }

    public function forgetCachedPermissions(): void
    {
        Cache::forget("users:{$this->id}:permission-set");
    }
}
