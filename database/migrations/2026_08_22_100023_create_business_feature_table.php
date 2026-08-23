<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Explicit per-business overrides of a package-granted feature — e.g. a
     * package includes "Multi Branch" but one business has it disabled, or a
     * package excludes "Restaurant Module" but one business has it manually
     * enabled. See FeatureAccessService for the resolution order.
     */
    public function up(): void
    {
        Schema::create('business_feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'feature_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_feature');
    }
};
