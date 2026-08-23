<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Categories and brands are business-scoped catalogs (not client-wide —
     * two businesses under the same client can each have their own
     * category tree). Products deliberately carry no pharmacy/electronics/
     * variant-specific columns — IMEI, batch, expiry, warranty, etc. are
     * meant to be modeled later via the existing custom_fields foundation
     * (Phase 7), which Product attaches to via HasCustomFields.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['business_id', 'slug']);
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['business_id', 'slug']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            // Percentage; null = inherit the business's default tax_rate
            // setting (see SettingsService) rather than a dedicated taxes
            // table, which nothing in this phase asks for yet.
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->string('unit')->default('pcs');
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['business_id', 'sku']);
            $table->unique(['business_id', 'barcode']);
            $table->index(['business_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
