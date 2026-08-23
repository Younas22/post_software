<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FeatureSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $features = [
            'POS',
            'Inventory',
            'Customers',
            'Suppliers',
            'Purchasing',
            'Reports',
            'Advanced Reports',
            'Multi Branch',
            'Custom Fields',
            'Restaurant Module',
            'Pharmacy Module',
            'Electronics Module',
            'Appointments',
        ];

        foreach ($features as $name) {
            Feature::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'status' => 'active']
            );
        }
    }
}
