<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * inventories holds ONE current-quantity row per (branch, product) — the
     * live balance every future Sale/Purchase/Return/Adjustment reads and
     * writes through StockService, always under a row lock. stock_movements
     * is the append-only ledger behind it: every change, whatever caused it,
     * is recorded here with the resulting balance, so the current quantity
     * is always reconstructable/auditable from history alone.
     */
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['purchase', 'sale', 'return', 'adjustment', 'transfer_in', 'transfer_out']);
            // Signed: positive adds to stock, negative removes.
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventories');
    }
};
