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
        Schema::table('clients', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('status');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('name');
            $table->string('email')->nullable()->after('phone');
            $table->text('address')->nullable()->after('email');
            $table->string('logo')->nullable()->after('address');
            $table->string('currency', 3)->default('USD')->after('logo');
            $table->string('timezone')->default('UTC')->after('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['phone', 'email', 'address', 'logo', 'currency', 'timezone']);
        });
    }
};
