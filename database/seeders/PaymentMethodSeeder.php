<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Global defaults (business_id null) every business can use out of the
     * box; a business may add its own on top later without a schema change.
     */
    public function run(): void
    {
        foreach (['Cash', 'Card', 'Bank', 'Other'] as $name) {
            PaymentMethod::query()->updateOrCreate(
                ['business_id' => null, 'slug' => Str::slug($name)],
                ['name' => $name, 'status' => 'active'],
            );
        }
    }
}
