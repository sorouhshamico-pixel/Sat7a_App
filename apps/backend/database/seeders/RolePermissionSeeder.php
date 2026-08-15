<?php

namespace Database\Seeders;

use App\Domain\Authorization\Enums\PermissionName;
use App\Domain\Authorization\Enums\RoleName;
use App\Domain\Authorization\Models\Permission;
use App\Domain\Authorization\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the role/permission catalog from docs/ROLES_PERMISSIONS.md. Safe to
 * run in any environment (production included) — it only creates/updates
 * the catalog, never touches user-to-role assignments.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(PermissionName::cases())
            ->mapWithKeys(fn (PermissionName $permission) => [
                $permission->value => Permission::query()->firstOrCreate(['name' => $permission->value]),
            ]);

        foreach ($this->roleDefinitions() as $roleNameValue => $permissionNames) {
            $roleName = RoleName::from($roleNameValue);

            $role = Role::query()->updateOrCreate(
                ['name' => $roleName->value],
                ['scope' => $roleName->scope()],
            );

            $role->permissions()->sync(
                $permissions->only($permissionNames)->pluck('id'),
            );
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function roleDefinitions(): array
    {
        $p = PermissionName::class;

        return [
            RoleName::ProviderOwner->value => [
                $p::FleetManage->value,
                $p::DriversManage->value,
                $p::OrdersView->value,
                $p::PricingView->value,
            ],
            RoleName::FleetManager->value => [
                $p::FleetManage->value,
                $p::DriversManage->value,
                $p::OrdersView->value,
            ],
            RoleName::Driver->value => [
                $p::OrdersView->value,
            ],

            RoleName::Dispatcher->value => [
                $p::OrdersView->value,
                $p::OrdersViewAll->value,
                $p::OrdersAssign->value,
            ],
            RoleName::CustomerSupport->value => [
                $p::OrdersView->value,
                $p::OrdersViewAll->value,
                $p::UsersView->value,
            ],
            RoleName::FinanceOfficer->value => [
                $p::PaymentsView->value,
                $p::PaymentsRefund->value,
                $p::SettlementsView->value,
                $p::SettlementsApprove->value,
                $p::SettlementsViewBankDetails->value,
                $p::PricingView->value,
            ],
            RoleName::ComplianceOfficer->value => [
                $p::ProvidersView->value,
                $p::ProvidersApprove->value,
                $p::ProvidersSuspend->value,
                $p::DocumentsView->value,
                $p::DocumentsViewSensitive->value,
                $p::DocumentsVerify->value,
                $p::DriversSuspend->value,
                $p::FleetSuspend->value,
            ],
            RoleName::OperationsManager->value => [
                $p::OrdersView->value,
                $p::OrdersViewAll->value,
                $p::OrdersAssign->value,
                $p::OrdersCancel->value,
                $p::ProvidersView->value,
                $p::UsersView->value,
                $p::DriversSuspend->value,
                $p::FleetSuspend->value,
                $p::PricingView->value,
            ],
            RoleName::Admin->value => array_map(
                fn (PermissionName $permission) => $permission->value,
                array_filter(
                    PermissionName::cases(),
                    fn (PermissionName $permission) => $permission !== PermissionName::RolesManage,
                ),
            ),
            RoleName::SuperAdmin->value => array_map(
                fn (PermissionName $permission) => $permission->value,
                PermissionName::cases(),
            ),
        ];
    }
}
