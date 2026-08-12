<?php

namespace App\Console\Commands;

use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Role;
use App\Domain\Users\Enums\UserType;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * The only way to create the very first super admin — there is no public
 * registration endpoint for admin/platform-staff accounts (see
 * docs/SECURITY.md §Authentication). Run once per environment; subsequent
 * admin accounts are created via role management by an existing admin.
 */
class CreateSuperAdminCommand extends Command
{
    protected $signature = 'admin:create-super-admin {email} {name} {--password=}';

    protected $description = 'Create the first super admin account (MFA setup is still required on first login)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->argument('name');
        $password = $this->option('password') ?? $this->secret('Password (min 12 characters)');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            ['email' => ['required', 'email'], 'name' => ['required', 'string'], 'password' => ['required', 'string', 'min:12']],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('A user with this email already exists.');

            return self::FAILURE;
        }

        $role = Role::query()->where('name', RoleName::SuperAdmin->value)->first();

        if ($role === null) {
            $this->error('The super_admin role does not exist yet — run `php artisan db:seed --class=RolePermissionSeeder` first.');

            return self::FAILURE;
        }

        $admin = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'user_type' => UserType::AdminStaff->value,
        ]);

        $admin->roles()->attach($role->id, ['assigned_by' => null]);

        $this->info("Super admin created: {$email}. MFA setup is required on first login.");

        return self::SUCCESS;
    }
}
