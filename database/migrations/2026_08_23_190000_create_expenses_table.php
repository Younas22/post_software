<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business expense tracking — was previously absent (ReportController's
     * `expenses` report returned 501 because of it). `category` is a free
     * string, not a lookup table, mirroring how Product.unit is a free
     * string today: no dedicated categories-of-expense table exists
     * elsewhere in the platform yet, so this doesn't invent one either.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('category');
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'expense_date']);
            $table->index(['branch_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
