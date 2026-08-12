<?php

namespace Database\Seeders;

use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Domain\Customers\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local development only — never run in staging/production (see
 * DatabaseSeeder and spec §138 "Database Seeders"). Fake data only; no
 * real identities, cards, or bank accounts (spec §140).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::factory()->admin()->create([
            'name' => 'Demo Super Admin',
            'email' => 'demo-admin@example.test',
        ]);

        $superAdminRole = Role::query()->where('name', RoleName::SuperAdmin->value)->first();
        if ($superAdminRole !== null) {
            $superAdmin->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }

        $demoCustomer = User::factory()->customer()->create([
            'name' => 'Demo Customer',
            'phone' => '+966500000001',
        ]);

        $customerProfile = new Customer([
            'notification_preferences' => Customer::defaultNotificationPreferences(),
        ]);
        $customerProfile->user_id = $demoCustomer->id;
        $customerProfile->save();

        $this->command->info('Demo super admin: demo-admin@example.test / password (MFA setup required on first login)');
    }
}
