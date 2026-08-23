<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->city().' Branch',
            'code' => strtoupper(fake()->unique()->bothify('BR-###')),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'is_main' => false,
            'status' => 'active',
        ];
    }
}
