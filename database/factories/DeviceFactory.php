<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => 'POS Terminal '.fake()->unique()->numberBetween(1, 999),
            'type' => 'pos',
            'device_identifier' => fake()->unique()->uuid(),
            'status' => 'active',
            'last_seen_at' => null,
        ];
    }

    /**
     * Derive client_id/business_id from the resolved branch_id when the
     * caller didn't explicitly set them — definition() never sets either,
     * so if one is already present here it can only be an explicit
     * override, which must win rather than being clobbered by the (possibly
     * unrelated, auto-generated) branch's own chain.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Device $device) {
            if ($device->client_id !== null && $device->business_id !== null) {
                return;
            }

            $branch = Branch::with('business')->find($device->branch_id);

            if ($branch) {
                $device->business_id ??= $branch->business_id;
                $device->client_id ??= $branch->business->client_id;
            }
        });
    }
}
