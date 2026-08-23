<?php

namespace Database\Factories;

use App\Models\BusinessType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Business>
 */
class BusinessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'client_id' => Client::factory(),
            'business_type_id' => BusinessType::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => 'active',
        ];
    }
}
