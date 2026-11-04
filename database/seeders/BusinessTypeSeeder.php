<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use Illuminate\Database\Seeder;

class BusinessTypeSeeder extends Seeder
{
    /**
     * Seeds every preset from config/business_types.php. Branding columns
     * (theme/terminology/modules) are only filled when still empty, so
     * re-running the seeder never wipes what the Super Admin customised.
     */
    public function run(): void
    {
        foreach (config('business_types.presets', []) as $slug => $preset) {
            $type = BusinessType::query()->firstOrNew(['slug' => $slug]);
            $type->name = $type->name ?: $preset['name'];
            $type->description = $type->description ?: ($preset['description'] ?? null);
            $type->icon = $type->icon ?: ($preset['icon'] ?? null);
            $type->theme = $type->theme ?: ($preset['theme'] ?? []);
            $type->terminology = $type->terminology ?: ($preset['terminology'] ?? []);
            $type->modules = $type->modules ?: array_merge(['hidden' => [], 'enabled' => []], $preset['modules'] ?? []);
            if (! $type->exists) {
                $type->is_active = true;
            }
            $type->save();
        }
    }
}
