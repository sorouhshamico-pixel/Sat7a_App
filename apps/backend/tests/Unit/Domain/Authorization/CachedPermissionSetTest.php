<?php

namespace Tests\Unit\Domain\Authorization;

use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression test for a real bug found while building Phase 17: with the
 * array cache store (what CACHE_STORE=array — every other test in this
 * suite — uses), a cache write/read round-trip never actually serializes
 * anything, so App\Domain\Authorization\Concerns\HasRoles::cachedPermissionSet()
 * caching Collection objects looked completely fine. Only a *second* read
 * against a real serializing store (redis, in production/dev) exposed
 * that Laravel's secure-by-default `config('cache.serializable_classes')`
 * (`false` — no class may be unserialized from cache) silently turns any
 * cached object into `__PHP_Incomplete_Class` on that second hit. Fixed
 * by caching plain arrays instead of Collections — see that method's
 * docblock. This test deliberately forces the redis store so a regression
 * here is caught even though the rest of the suite runs on the array
 * store.
 */
class CachedPermissionSetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        config(['cache.default' => 'redis']);

        // Redis isn't reset by RefreshDatabase's transaction rollback (only
        // the database is) — a leftover key from an earlier run, keyed by
        // an auto-increment id a fresh RefreshDatabase run can easily
        // reuse, would otherwise leak stale role data into this test.
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_has_permission_survives_a_real_cache_write_then_a_separate_read(): void
    {
        $user = User::factory()->admin()->create();
        $user->roles()->attach(Role::where('name', RoleName::FinanceOfficer->value)->firstOrFail());

        // First call: cache miss, writes the permission set.
        $this->assertTrue($user->hasPermission('settlements.view'));
        $this->assertFalse($user->hasPermission('roles.manage'));

        // Second call on a *fresh* model instance: cache hit, forces an
        // actual unserialize() of whatever the first call wrote — this is
        // exactly the read that returned __PHP_Incomplete_Class before the
        // fix.
        $sameUser = User::query()->findOrFail($user->id);
        $this->assertTrue($sameUser->hasPermission('settlements.view'));
        $this->assertTrue($sameUser->hasRole(RoleName::FinanceOfficer->value));
        $this->assertFalse($sameUser->hasRole(RoleName::Dispatcher->value));
    }
}
