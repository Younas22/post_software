<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `open_marker` is the standard MySQL "conditional unique index" trick:
     * it's set to 1 while status = 'open' and reset to NULL on close (a
     * unique index treats every NULL as distinct, but collides on two equal
     * non-null values). unique(user_id, branch_id, open_marker) therefore
     * makes "one open session per cashier per branch" a real DATABASE
     * constraint, not just an application-level check that a race could
     * slip past — see CashSessionService::open().
     */
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('closing_cash', 12, 2)->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('actual_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->unsignedTinyInteger('open_marker')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'branch_id', 'open_marker']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['cash_in', 'cash_out']);
            $table->decimal('amount', 12, 2);
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
    }
};
