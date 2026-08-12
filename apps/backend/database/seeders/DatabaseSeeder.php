<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Safe in every environment, including production — only creates/
        // updates the role/permission catalog, never touches user data.
        $this->call(RolePermissionSeeder::class);
        $this->call(CitySeeder::class);

        // Fake demo data only, local development only (see docs/SECURITY.md
        // §Data classification and spec §138 "Database Seeders").
        if ($this->command->getLaravel()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
