<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Package;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PackageSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * These are development defaults only — real pricing/limits/feature sets
     * remain fully editable from the database (Super Admin panel, later phase).
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Starter',
                'price' => 19.99,
                'max_businesses' => 1,
                'max_branches' => 1,
                'max_users' => 3,
                'max_devices' => 1,
                'features' => ['POS', 'Customers', 'Reports'],
            ],
            [
                'name' => 'Business',
                'price' => 49.99,
                'max_businesses' => 1,
                'max_branches' => 3,
                'max_users' => 10,
                'max_devices' => 5,
                'features' => ['POS', 'Inventory', 'Customers', 'Suppliers', 'Purchasing', 'Reports', 'Multi Branch'],
            ],
            [
                'name' => 'Professional',
                'price' => 99.99,
                'max_businesses' => 3,
                'max_branches' => 10,
                'max_users' => 25,
                'max_devices' => 15,
                'features' => [
                    'POS', 'Inventory', 'Customers', 'Suppliers', 'Purchasing',
                    'Reports', 'Advanced Reports', 'Multi Branch', 'Custom Fields', 'Appointments',
                ],
            ],
            [
                'name' => 'Enterprise',
                'price' => 199.99,
                'max_businesses' => null,
                'max_branches' => null,
                'max_users' => null,
                'max_devices' => null,
                'features' => Feature::query()->pluck('name')->all(),
            ],
        ];

        foreach ($packages as $definition) {
            $featureNames = $definition['features'];
            unset($definition['features']);

            $package = Package::query()->updateOrCreate(
                ['slug' => Str::slug($definition['name'])],
                [...$definition, 'billing_cycle' => 'monthly', 'status' => 'active']
            );

            $featureIds = Feature::query()->whereIn('name', $featureNames)->pluck('id');
            $package->features()->sync($featureIds);
        }
    }
}
