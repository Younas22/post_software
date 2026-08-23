<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('platform')->default('web')->after('type');
            $table->timestamp('activated_at')->nullable()->after('last_seen_at');
        });

        // `status` needs a third value ('blocked') that the original
        // enum('active','inactive') doesn't allow. Altering a MySQL ENUM via
        // Schema::table()->change() requires doctrine/dbal, which this
        // project doesn't depend on — so it's converted to a plain VARCHAR
        // via raw SQL instead (MySQL only — SQLite, used in tests, has no
        // MODIFY COLUMN syntax and doesn't enforce the original CHECK
        // constraint here anyway), with the allowed values enforced in the
        // application layer (Device::STATUSES / form request validation).
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE devices MODIFY status VARCHAR(20) NOT NULL DEFAULT 'inactive'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("UPDATE devices SET status = 'inactive' WHERE status NOT IN ('active', 'inactive')");
            DB::statement("ALTER TABLE devices MODIFY status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
        }

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['platform', 'activated_at']);
        });
    }
};
