<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * These are development defaults only, editable from the database. Note
     * that Super Admin's full access is also enforced in code via the
     * `is_super_admin` flag (see User::hasPermission()) independent of this
     * mapping — this row exists mainly so the assignment is visible/auditable.
     */
    public function run(): void
    {
        $map = [
            'super-admin' => [
                'dashboard.view',
                'clients.view', 'clients.create', 'clients.update', 'clients.delete',
                'businesses.view', 'businesses.create', 'businesses.update',
                'users.view', 'users.create', 'users.update',
                'packages.view', 'packages.create', 'packages.update', 'packages.delete',
                'subscriptions.view', 'subscriptions.manage',
                'devices.view', 'devices.manage',
                'settings.view', 'settings.update',
                'activity_logs.view',
                'products.view', 'products.create', 'products.update', 'products.delete',
                'inventory.view', 'inventory.adjust',
                'customers.view', 'customers.create', 'customers.update', 'customers.delete',
                'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
                'purchases.view', 'purchases.create', 'purchases.update', 'purchases.cancel',
                'sales.view', 'sales.create',
                'returns.view', 'returns.process',
                'cash_sessions.view', 'cash_sessions.operate', 'cash_sessions.manage',
                'reports.view',
                'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete',
                'installments.view', 'installments.create', 'installments.collect', 'installments.cancel',
            ],
            'client-owner' => [
                'dashboard.view',
                'businesses.view', 'businesses.create', 'businesses.update',
                'users.view', 'users.create', 'users.update',
                'subscriptions.view',
                'devices.view', 'devices.manage',
                'settings.view', 'settings.update',
                'activity_logs.view',
                'products.view', 'products.create', 'products.update', 'products.delete',
                'inventory.view', 'inventory.adjust',
                'customers.view', 'customers.create', 'customers.update', 'customers.delete',
                'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
                'purchases.view', 'purchases.create', 'purchases.update', 'purchases.cancel',
                'sales.view', 'sales.create',
                'returns.view', 'returns.process',
                'cash_sessions.view', 'cash_sessions.operate', 'cash_sessions.manage',
                'reports.view',
                'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete',
                'installments.view', 'installments.create', 'installments.collect', 'installments.cancel',
            ],
            'business-admin' => [
                'dashboard.view',
                'businesses.view', 'businesses.update',
                'users.view', 'users.create', 'users.update',
                'devices.view', 'devices.manage',
                'settings.view', 'settings.update',
                'products.view', 'products.create', 'products.update', 'products.delete',
                'inventory.view', 'inventory.adjust',
                'customers.view', 'customers.create', 'customers.update', 'customers.delete',
                'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
                'purchases.view', 'purchases.create', 'purchases.update', 'purchases.cancel',
                'sales.view', 'sales.create',
                'returns.view', 'returns.process',
                'cash_sessions.view', 'cash_sessions.operate', 'cash_sessions.manage',
                'reports.view',
                'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete',
                'installments.view', 'installments.create', 'installments.collect', 'installments.cancel',
            ],
            'manager' => [
                'dashboard.view',
                'businesses.view',
                'users.view',
                'devices.view',
                'settings.view',
                'products.view', 'products.create', 'products.update',
                'inventory.view', 'inventory.adjust',
                'customers.view', 'customers.create', 'customers.update',
                'suppliers.view', 'suppliers.create', 'suppliers.update',
                'purchases.view', 'purchases.create', 'purchases.update',
                'sales.view', 'sales.create',
                'returns.view', 'returns.process',
                'cash_sessions.view', 'cash_sessions.operate', 'cash_sessions.manage',
                'reports.view',
                'expenses.view', 'expenses.create', 'expenses.update',
                'installments.view', 'installments.create', 'installments.collect',
            ],
            'cashier' => [
                'dashboard.view',
                'devices.view',
                'products.view',
                'inventory.view',
                'customers.view', 'customers.create',
                'sales.view', 'sales.create',
                'returns.view', 'returns.process',
                'cash_sessions.operate',
                'installments.view', 'installments.collect',
            ],
        ];

        foreach ($map as $roleSlug => $permissionSlugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();

            if (! $role) {
                continue;
            }

            $permissionIds = Permission::query()->whereIn('slug', $permissionSlugs)->pluck('id');
            $role->permissions()->sync($permissionIds);
        }
    }
}
