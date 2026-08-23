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
        Schema::table('settings', function (Blueprint $table) {
            // Null client_id + business_id + branch_id = system/global setting.
            // A branch-level row scopes further than its business; a
            // business-level row further than its client. See
            // SettingsService::resolve() for the cascade this enables.
            $table->foreignId('branch_id')->nullable()->after('business_id')->constrained()->cascadeOnDelete();
            $table->index(['client_id', 'business_id', 'branch_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'business_id', 'branch_id', 'key']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
