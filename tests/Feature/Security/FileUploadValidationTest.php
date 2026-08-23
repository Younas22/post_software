<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\BusinessType;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FileUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    public function test_business_logo_rejects_a_non_image_file(): void
    {
        $client = Client::factory()->create();
        $businessType = BusinessType::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.businesses.store'), [
            'client_id' => $client->id,
            'business_type_id' => $businessType->id,
            'name' => 'Test Business',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'status' => 'active',
            'logo' => UploadedFile::fake()->create('malicious.php', 10, 'application/x-php'),
        ]);

        $response->assertSessionHasErrors('logo');
    }

    public function test_business_logo_rejects_svg_specifically(): void
    {
        $client = Client::factory()->create();
        $businessType = BusinessType::factory()->create();

        // SVG can carry an embedded <script> — Laravel's plain 'image' rule
        // excludes it unless allow_svg is explicitly passed (it isn't here).
        $response = $this->actingAs($this->admin())->post(route('admin.businesses.store'), [
            'client_id' => $client->id,
            'business_type_id' => $businessType->id,
            'name' => 'Test Business',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'status' => 'active',
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ]);

        $response->assertSessionHasErrors('logo');
    }

    public function test_business_logo_rejects_oversized_dimensions(): void
    {
        $client = Client::factory()->create();
        $businessType = BusinessType::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.businesses.store'), [
            'client_id' => $client->id,
            'business_type_id' => $businessType->id,
            'name' => 'Test Business',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'status' => 'active',
            'logo' => UploadedFile::fake()->image('huge.png', 5000, 5000),
        ]);

        $response->assertSessionHasErrors('logo');
    }

    public function test_business_logo_accepts_a_reasonable_image(): void
    {
        $client = Client::factory()->create();
        $businessType = BusinessType::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.businesses.store'), [
            'client_id' => $client->id,
            'business_type_id' => $businessType->id,
            'name' => 'Test Business',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'status' => 'active',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]);

        $response->assertSessionDoesntHaveErrors('logo');
        $this->assertDatabaseHas('businesses', ['name' => 'Test Business']);
    }
}
