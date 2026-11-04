<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private function api(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('t')->plainTextToken);
    }

    public function test_user_uploads_replaces_and_removes_their_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $first = $this->api($user)->post('/api/v1/user/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg', 400, 400)], ['Accept' => 'application/json'])
            ->assertOk()->json('data.avatar_url');
        $this->assertNotNull($first);
        $path = $user->fresh()->avatar;
        Storage::disk('public')->assertExists($path);

        $this->api($user)->getJson('/api/v1/user')->assertJsonPath('data.user.avatar_url', $first);

        $this->api($user)->post('/api/v1/user/avatar', ['avatar' => UploadedFile::fake()->image('new.png')], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertMissing($path);

        $this->api($user)->deleteJson('/api/v1/user/avatar')->assertOk()->assertJsonPath('data.avatar_url', null);
        $this->assertNull($user->fresh()->avatar);
    }

    public function test_non_image_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->api($user)->post('/api/v1/user/avatar', ['avatar' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('avatar');
    }
}
