<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use Illuminate\Database\Seeder;

class BusinessTypeSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Grocery', 'slug' => 'grocery'],
            ['name' => 'Restaurant', 'slug' => 'restaurant'],
            ['name' => 'Pharmacy', 'slug' => 'pharmacy'],
            ['name' => 'Electronics', 'slug' => 'electronics'],
            ['name' => 'Clothing', 'slug' => 'clothing'],
            ['name' => 'Salon', 'slug' => 'salon'],
            ['name' => 'Hardware', 'slug' => 'hardware'],
            ['name' => 'Services', 'slug' => 'services'],
            ['name' => 'Wholesale', 'slug' => 'wholesale'],
            ['name' => 'Other', 'slug' => 'other'],
        ];

        foreach ($types as $type) {
            BusinessType::query()->updateOrCreate(
                ['slug' => $type['slug']],
                ['name' => $type['name'], 'is_active' => true]
            );
        }
    }
}
