<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per client-generated transaction attempt. Uniqueness is
     * scoped to (business_id, client_transaction_id) — the DATABASE
     * constraint, not just an application-level check, is what actually
     * prevents two concurrent identical requests from both succeeding (see
     * IdempotencyService::handle()). A business can never collide with
     * another business's key, by construction: the unique index includes
     * business_id, so the same client_transaction_id string reused by a
     * different business is simply a different row.
     *
     * response_status/response_body are filled in only once the wrapped
     * operation actually completes; a null response_status means a prior
     * attempt started but never finished (crashed, timed out, ...), which
     * is exactly the case a same-key retry is allowed to actually execute
     * and complete, rather than being replayed.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_transaction_id');
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->nullableMorphs('subject');
            $table->timestamps();

            $table->unique(['business_id', 'client_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
