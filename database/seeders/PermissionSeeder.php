<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $slugs = [
            'dashboard.view',
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.delete',
            'businesses.view',
            'businesses.create',
            'businesses.update',
            'users.view',
            'users.create',
            'users.update',
            'packages.view',
            'packages.create',
            'packages.update',
            'packages.delete',
            'subscriptions.view',
            'subscriptions.manage',
            'devices.view',
            'devices.manage',
            'settings.view',
            'settings.update',
            'activity_logs.view',
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'inventory.view',
            'inventory.adjust',
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'suppliers.delete',
            'purchases.view',
            'purchases.create',
            'purchases.update',
            'purchases.cancel',
            'sales.view',
            'sales.create',
            'returns.view',
            'returns.process',
            'cash_sessions.view',
            'cash_sessions.operate',
            'cash_sessions.manage',
            'reports.view',
            'expenses.view',
            'expenses.create',
            'expenses.update',
            'expenses.delete',
            'installments.view',
            'installments.create',
            'installments.collect',
            'installments.cancel',
        ];

        foreach ($slugs as $slug) {
            Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => Str::of($slug)->replace('.', ' ')->replace('_', ' ')->title()]
            );
        }
    }
}
