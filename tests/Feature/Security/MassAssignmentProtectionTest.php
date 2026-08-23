<?php

namespace Tests\Feature\Security;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MassAssignmentProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_super_admin_cannot_be_set_via_mass_assignment(): void
    {
        $user = new User();
        $user->fill([
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'irrelevant',
            'is_super_admin' => true,
        ]);
        $user->save();

        $this->assertFalse($user->fresh()->is_super_admin);
    }

    public function test_tenant_identity_fields_cannot_be_set_via_mass_assignment(): void
    {
        $client = Client::factory()->create();

        $user = new User();
        $user->fill([
            'name' => 'Attacker',
            'email' => 'attacker2@example.com',
            'password' => 'irrelevant',
            'client_id' => $client->id,
        ]);
        $user->save();

        $this->assertNull($user->fresh()->client_id);
    }

    public function test_trusted_server_side_code_can_still_set_these_fields_via_forcefill(): void
    {
        $client = Client::factory()->create();

        $user = new User();
        $user->forceFill([
            'name' => 'Seeded Admin',
            'email' => 'seeded@example.com',
            'password' => 'irrelevant',
            'is_super_admin' => true,
            'client_id' => $client->id,
        ])->save();

        $fresh = $user->fresh();
        $this->assertTrue($fresh->is_super_admin);
        $this->assertSame($client->id, $fresh->client_id);
    }
}
