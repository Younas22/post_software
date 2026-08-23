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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('password');
            $table->foreignId('client_id')->nullable()->after('is_super_admin')
                ->constrained()->nullOnDelete();
            $table->foreignId('business_id')->nullable()->after('client_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('business_id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('business_id');
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn('is_super_admin');
        });
    }
};
