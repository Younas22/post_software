<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Installment (lease / easy-payment) selling for business types that
     * enable the `installments` module.
     *
     *  installment_plans          one credit sale to a customer
     *  installment_plan_items     the goods handed over (stock leaves on create)
     *  installment_schedules      the due dates the balance is split into
     *  installment_payments       every rupee received (down payment included)
     *
     * Financial history: plans are cancelled, never deleted.
     */
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('plan_number');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('markup_percent', 6, 2)->default(0);
            $table->decimal('markup_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('down_payment', 14, 2)->default(0);
            $table->decimal('financed_amount', 14, 2)->default(0);
            $table->unsignedSmallInteger('installments_count');
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly'])->default('monthly');
            $table->decimal('installment_amount', 14, 2)->default(0);
            $table->date('start_date');
            $table->date('first_due_date');
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->string('guarantor_name')->nullable();
            $table->string('guarantor_phone')->nullable();
            $table->string('guarantor_cnic')->nullable();
            $table->string('guarantor_address')->nullable();
            $table->string('customer_cnic')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'plan_number']);
            $table->index(['business_id', 'status']);
        });

        Schema::create('installment_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 14, 2);
            $table->string('serial_number')->nullable();
            $table->timestamps();
        });

        Schema::create('installment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['installment_plan_id', 'due_date']);
        });

        Schema::create('installment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installment_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_number');
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->boolean('is_down_payment')->default(false);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'payment_date']);
        });

        $this->seedPermissions();
    }

    /**
     * Adds the installments.* permissions and grants them to the standard
     * roles WITHOUT touching any other role→permission assignment (unlike
     * re-running RolePermissionSeeder, which would sync()).
     */
    private function seedPermissions(): void
    {
        $now = now();
        $slugs = [
            'installments.view' => 'Installments View',
            'installments.create' => 'Installments Create',
            'installments.collect' => 'Installments Collect',
            'installments.cancel' => 'Installments Cancel',
        ];

        foreach ($slugs as $slug => $name) {
            if (! DB::table('permissions')->where('slug', $slug)->exists()) {
                DB::table('permissions')->insert(['slug' => $slug, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $grants = [
            'super-admin' => array_keys($slugs),
            'client-owner' => array_keys($slugs),
            'business-admin' => array_keys($slugs),
            'manager' => ['installments.view', 'installments.create', 'installments.collect'],
            'cashier' => ['installments.view', 'installments.collect'],
        ];

        foreach ($grants as $roleSlug => $permissionSlugs) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
            if (! $roleId) {
                continue;
            }
            foreach (DB::table('permissions')->whereIn('slug', $permissionSlugs)->pluck('id') as $permissionId) {
                $exists = DB::table('role_permission')->where('role_id', $roleId)->where('permission_id', $permissionId)->exists();
                if (! $exists) {
                    DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_payments');
        Schema::dropIfExists('installment_schedules');
        Schema::dropIfExists('installment_plan_items');
        Schema::dropIfExists('installment_plans');

        $ids = DB::table('permissions')->where('slug', 'like', 'installments.%')->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
