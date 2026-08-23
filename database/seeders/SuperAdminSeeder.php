<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Development-only credentials. Never ship this seeder's password to a
     * real environment — override via env or rotate immediately after use.
     */
    public function run(): void
    {
        $email = config('app.super_admin_email', 'admin@possaas.test');
        $password = config('app.super_admin_password', 'password');

        // is_super_admin is deliberately excluded from User::$fillable (see
        // the model), so it's set here via forceFill rather than
        // updateOrCreate's mass-assignment path.
        $admin = User::query()->firstOrNew(['email' => $email]);
        $admin->forceFill([
            'name' => 'Super Admin',
            'password' => Hash::make($password),
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ])->save();

        $superAdminRole = Role::query()->where('slug', 'super-admin')->first();

        if ($superAdminRole) {
            $admin->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }
    }
}
