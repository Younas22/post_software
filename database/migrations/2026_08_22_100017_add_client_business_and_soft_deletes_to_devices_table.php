<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('client_id')->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->after('client_id')->constrained()->cascadeOnDelete();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('business_id');
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
