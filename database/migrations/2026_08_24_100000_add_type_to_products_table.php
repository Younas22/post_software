<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds a `type` column so the POS can sell non-inventory "services"
     * (consulting, labor, delivery fees, etc.) through the exact same
     * Product/Sale pipeline as physical products — same catalog, same
     * pricing/tax, same sales/receipt/report code — rather than a parallel
     * Service model+table+controller. `product` remains the default so
     * every existing row is unaffected. `type = 'service'` is the single
     * switch SaleService/StockService check to skip inventory movement —
     * see App\Services\SaleService and App\Services\StockService.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('type', ['product', 'service'])->default('product')->after('brand_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
