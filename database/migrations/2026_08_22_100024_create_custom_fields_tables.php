<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Foundation for future dynamic fields (e.g. IMEI/Warranty/Batch on a
     * product, custom attributes on a customer) — deliberately generic so
     * none of those concrete field names are ever hard-coded into the
     * schema. No Product/Customer model exists yet (out of scope per the
     * master context); `entity_type` names them only as future string
     * labels, and `custom_field_values` is polymorphic so it can attach to
     * whatever model eventually plays that role, without another migration.
     */
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            // Null = a platform-wide field template; set = business-specific.
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('entity_type'); // e.g. 'product', 'customer', 'business' — free-form, not an enum
            $table->string('key');
            $table->string('label');
            $table->string('type')->default('text'); // text, number, date, select, boolean
            $table->json('options')->nullable(); // choices for type=select
            $table->boolean('is_required')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['business_id', 'entity_type', 'key']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            // Explicit short index name — the auto-generated one exceeds
            // MySQL's 64-character identifier limit on this column name.
            $table->morphs('custom_fieldable', 'custom_field_values_morph_index');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['custom_field_id', 'custom_fieldable_type', 'custom_fieldable_id'], 'custom_field_values_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_fields');
    }
};
