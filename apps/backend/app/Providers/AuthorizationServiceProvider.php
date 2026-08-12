<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Lets every permission slug in the catalog (docs/ROLES_PERMISSIONS.md,
 * App\Domain\Authorization\Enums\PermissionName) double as a Laravel
 * authorization ability, so `$user->can('orders.view_all')`,
 * `Gate::authorize('orders.view_all')`, and Policy checks all work without
 * a manual Gate::define() per permission. Returns null (not false) when the
 * user lacks the permission, so a more specific Policy can still run
 * afterwards — this only ever grants, never denies, an ability.
 */
class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermission($ability) ? true : null;
        });
    }
}
