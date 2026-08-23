<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' Plan';

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 0, 200),
            'billing_cycle' => 'monthly',
            'max_businesses' => 1,
            'max_branches' => 1,
            'max_users' => 5,
            'max_devices' => 2,
            'status' => 'active',
        ];
    }
}
