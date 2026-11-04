<?php

namespace Tests\Feature\Admin;

use App\Models\Business;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientLoginColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_list_shows_owner_login_and_only_the_demo_password(): void
    {
        $this->seed(RoleSeeder::class);
        config(['app.demo_client_password' => 'demo0987']);
        $ownerRole = Role::query()->where('slug', 'client-owner')->firstOrFail();

        $demo = Client::factory()->create(['name' => 'Demo Client']);
        Business::factory()->create(['client_id' => $demo->id, 'name' => 'Demo Shop']);
        $demoOwner = User::factory()->create(['client_id' => $demo->id, 'email' => 'demo-owner@example.test', 'password' => Hash::make('demo0987')]);
        $demoOwner->roles()->attach($ownerRole);

        $real = Client::factory()->create(['name' => 'Real Client']);
        $realOwner = User::factory()->create(['client_id' => $real->id, 'email' => 'real-owner@example.test', 'password' => Hash::make('S3cret-Real!')]);
        $realOwner->roles()->attach($ownerRole);

        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Demo Shop')
            ->assertSee('demo-owner@example.test')
            ->assertSee('demo0987')
            ->assertSee('real-owner@example.test')
            ->assertSee('custom (hidden)')
            ->assertDontSee('S3cret-Real!');
    }
}
