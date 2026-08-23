<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\Client;
use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoClientSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Development-only demo tenant so the /client area and Client Owner role
     * can be logged into and tested without hand-crafting data first. Never
     * ship this seeder's password to a real environment.
     */
    public function run(): void
    {
        $client = Client::query()->updateOrCreate(
            ['email' => 'demo.client@possaas.test'],
            ['name' => 'Demo Retail Co', 'status' => 'active']
        );

        $grocery = BusinessType::query()->where('slug', 'grocery')->first();

        $business = Business::query()->updateOrCreate(
            ['client_id' => $client->id, 'slug' => 'demo-retail-co'],
            ['business_type_id' => $grocery?->id, 'name' => 'Demo Retail Co', 'status' => 'active']
        );

        $branch = Branch::query()->updateOrCreate(
            ['business_id' => $business->id, 'name' => 'Main Branch'],
            ['is_main' => true, 'status' => 'active']
        );

        $starter = Package::query()->where('slug', 'starter')->first();

        if ($starter) {
            Subscription::query()->updateOrCreate(
                ['client_id' => $client->id, 'package_id' => $starter->id],
                ['status' => 'trial', 'trial_ends_at' => now()->addDays(14), 'starts_at' => now()]
            );
        }

        $email = config('app.demo_client_email', 'demo.owner@possaas.test');
        $password = config('app.demo_client_password', 'password');

        // is_super_admin/client_id/business_id/branch_id are deliberately
        // excluded from User::$fillable (see the model), so they're set
        // here via forceFill rather than updateOrCreate's mass-assignment
        // path.
        $owner = User::query()->firstOrNew(['email' => $email]);
        $owner->forceFill([
            'name' => 'Demo Owner',
            'password' => Hash::make($password),
            'is_super_admin' => false,
            'client_id' => $client->id,
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'email_verified_at' => now(),
        ])->save();

        $clientOwnerRole = Role::query()->where('slug', 'client-owner')->first();

        if ($clientOwnerRole) {
            $owner->roles()->syncWithoutDetaching([$clientOwnerRole->id]);
        }
    }
}
