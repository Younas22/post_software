<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-type branding so one codebase looks/talks like a trade-specific
     * product: theme (colours + branded login copy/image), terminology
     * ("Patient" instead of "Customer") and modules (hide / enable
     * screens). See config/business_types.php.
     *
     * Existing rows are back-filled from the presets — and the new preset
     * types (Dental Clinic, Installment Store) are created — but a value
     * the Super Admin already set is never overwritten.
     */
    public function up(): void
    {
        Schema::table('business_types', function (Blueprint $table) {
            $table->json('theme')->nullable()->after('icon');
            $table->json('terminology')->nullable()->after('theme');
            $table->json('modules')->nullable()->after('terminology');
        });

        $presets = config('business_types.presets', []);
        $now = now();

        foreach ($presets as $slug => $preset) {
            $row = DB::table('business_types')->where('slug', $slug)->first();

            $payload = [
                'theme' => json_encode($preset['theme'] ?? []),
                'terminology' => json_encode($preset['terminology'] ?? []),
                'modules' => json_encode(array_merge(['hidden' => [], 'enabled' => []], $preset['modules'] ?? [])),
                'updated_at' => $now,
            ];

            if ($row) {
                $update = ['updated_at' => $now];
                foreach (['theme', 'terminology', 'modules'] as $column) {
                    if ($row->{$column} === null) {
                        $update[$column] = $payload[$column];
                    }
                }
                if (! $row->icon && ! empty($preset['icon'])) {
                    $update['icon'] = $preset['icon'];
                }
                if (! $row->description && ! empty($preset['description'])) {
                    $update['description'] = $preset['description'];
                }
                DB::table('business_types')->where('id', $row->id)->update($update);
            } else {
                DB::table('business_types')->insert($payload + [
                    'name' => $preset['name'],
                    'slug' => $slug,
                    'description' => $preset['description'] ?? null,
                    'icon' => $preset['icon'] ?? null,
                    'is_active' => true,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('business_types', function (Blueprint $table) {
            $table->dropColumn(['theme', 'terminology', 'modules']);
        });
    }
};
