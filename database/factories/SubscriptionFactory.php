<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'package_id' => Package::factory(),
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'starts_at' => now(),
            'ends_at' => null,
            'cancelled_at' => null,
        ];
    }
}
