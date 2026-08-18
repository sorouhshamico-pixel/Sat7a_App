<?php

namespace App\Domain\Authorization\Concerns;

use App\Domain\Authorization\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        return in_array($roleName, $this->cachedPermissionSet()['roles'], true);
    }

    public function hasPermission(string $permissionName): bool
    {
        return in_array($permissionName, $this->cachedPermissionSet()['permissions'], true);
    }

    /**
     * Permission checks happen on nearly every authenticated request (via
     * Gate::before — see AuthorizationServiceProvider), so the resolved
     * role/permission names are cached briefly per-user rather than
     * re-joining role_user/permission_role on every single check within a
     * request.
     *
     * Deliberately returns plain arrays, not Collections — see
     * docs/SECURITY.md §Cache deserialization. Laravel's default
     * `config('cache.serializable_classes')` is `false` (no class may be
     * unserialized out of the cache, a hardening against gadget-chain
     * attacks — see that config file's own comment), which silently turns
     * any cached object into a useless `__PHP_Incomplete_Class` on every
     * cache *hit* the moment a real serializing store (redis, memcached —
     * anything but the array/array-like test store) is in use. Caching
     * plain arrays here sidesteps the restriction entirely rather than
     * loosening it, since there's never a legitimate reason to cache
     * Collection objects instead of their underlying array.
     *
     * @return array{roles: list<string>, permissions: list<string>}
     */
    private function cachedPermissionSet(): array
    {
        return Cache::remember(
            "users:{$this->id}:permission-set",
            now()->addMinutes(5),
            function () {
                $roles = $this->roles()->with('permissions')->get();

                return [
                    'roles' => $roles->pluck('name')->all(),
                    'permissions' => $roles->flatMap(fn (Role $role) => $role->permissions->pluck('name'))->unique()->values()->all(),
                ];
            },
        );
    }

    public function forgetCachedPermissions(): void
    {
        Cache::forget("users:{$this->id}:permission-set");
    }
}
