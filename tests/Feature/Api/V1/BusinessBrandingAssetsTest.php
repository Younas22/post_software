<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessBrandingAssetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    /** @return array{0: Business, 1: User} */
    private function owner(): array
    {
        $client = Client::factory()->create();
        $business = Business::factory()->create(['client_id' => $client->id, 'currency' => 'PKR', 'timezone' => 'Asia/Karachi']);
        Branch::factory()->create(['business_id' => $business->id]);
        $owner = User::factory()->create(['client_id' => $client->id, 'business_id' => $business->id]);
        $owner->roles()->attach(Role::query()->where('slug', 'client-owner')->firstOrFail());

        return [$business, $owner];
    }

    private function base(Business $business): array
    {
        return ['name' => $business->name, 'currency' => 'PKR', 'timezone' => 'Asia/Karachi'];
    }

    public function test_owner_uploads_logo_and_favicon_and_config_exposes_them(): void
    {
        Storage::fake('public');
        [$business, $owner] = $this->owner();
        $auth = ['Authorization' => 'Bearer '.$owner->createToken('t')->plainTextToken, 'Accept' => 'application/json'];

        // multipart travels as POST + _method=PUT (PHP only parses multipart bodies on POST)
        $this->post('/api/v1/business', $this->base($business) + [
            '_method' => 'PUT',
            'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
            'favicon' => UploadedFile::fake()->image('icon.png', 64, 64),
        ], $auth)->assertOk()
            ->assertJsonPath('data.favicon_url', fn ($url) => is_string($url) && str_contains($url, 'business-favicons/'))
            ->assertJsonPath('data.logo_url', fn ($url) => is_string($url) && str_contains($url, 'business-logos/'));

        $business->refresh();
        Storage::disk('public')->assertExists([$business->logo, $business->favicon]);

        $this->getJson('/api/v1/config', $auth)
            ->assertJsonPath('data.business.favicon_url', Storage::disk('public')->url($business->favicon));

        // remove favicon only — logo stays
        $favicon = $business->favicon;
        $this->putJson('/api/v1/business', $this->base($business) + ['remove_favicon' => true], $auth)
            ->assertOk()->assertJsonPath('data.favicon_url', null);
        Storage::disk('public')->assertMissing($favicon);
        $this->assertNotNull($business->fresh()->logo);
    }

    public function test_svg_favicon_is_rejected(): void
    {
        [$business, $owner] = $this->owner();

        $this->post('/api/v1/business', $this->base($business) + [
            '_method' => 'PUT',
            'favicon' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ], ['Authorization' => 'Bearer '.$owner->createToken('t')->plainTextToken, 'Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('favicon');
    }
}
