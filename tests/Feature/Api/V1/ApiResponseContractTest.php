<?php

namespace Tests\Feature\Api\V1;

use App\Models\Client;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/**
 * Verifies the standard API envelope end-to-end, and that every exception
 * type routes/api.php can encounter renders as clean JSON — never an HTML
 * error page, and never a stack trace/SQL/file path.
 *
 * A few cases (403/404-model/409/500) have no real endpoint that naturally
 * triggers them yet (no protected resource, no findOrFail lookup exists in
 * the API surface today) — for those only, this test registers a throwaway
 * route inside the test itself that deliberately throws the exception in
 * question, purely to exercise ApiExceptionRenderer at the real HTTP layer.
 * This adds no permanent API surface; routes/api.php is untouched.
 *
 * Note: assertions check `message`/`success`/`errors` individually via
 * ->json('key') rather than assertExactJson() — PHPUnit's JSON string-diff
 * for assertExactJson distinguishes an empty object `{}` from an empty
 * array `[]` at the wire-format level, but ->json('errors') decodes both
 * the same way, which is what these tests actually care about.
 */
class ApiResponseContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_envelope_matches_the_standard_contract(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_validation_error_returns_422_with_standard_envelope(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)->assertJsonStructure(['success', 'message', 'errors']);
        $this->assertFalse($response->json('success'));
        $this->assertArrayHasKey('email', $response->json('errors'));
        $this->assertArrayHasKey('password', $response->json('errors'));
        $this->assertArrayHasKey('device_name', $response->json('errors'));
    }

    public function test_unauthenticated_returns_401_with_standard_envelope(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(401);
        $this->assertFalse($response->json('success'));
        $this->assertSame('Unauthenticated.', $response->json('message'));
        $this->assertSame([], $response->json('errors'));
    }

    public function test_forbidden_returns_403_with_standard_envelope(): void
    {
        Route::middleware('api')->get('/api/v1/__test/forbidden', function () {
            throw new AuthorizationException('You may not do that.');
        });

        $response = $this->getJson('/api/v1/__test/forbidden');

        $response->assertStatus(403);
        $this->assertFalse($response->json('success'));
        $this->assertSame('You may not do that.', $response->json('message'));
        $this->assertSame([], $response->json('errors'));
    }

    public function test_undefined_route_returns_404_with_standard_envelope(): void
    {
        $response = $this->getJson('/api/v1/this-route-does-not-exist');

        $response->assertStatus(404)->assertJsonStructure(['success', 'message', 'errors']);
        $this->assertFalse($response->json('success'));
        $this->assertSame('The requested endpoint was not found.', $response->json('message'));
        // Never an HTML error page.
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    public function test_model_not_found_returns_404_with_standard_envelope(): void
    {
        Route::middleware('api')->get('/api/v1/__test/missing-model', function () {
            Client::query()->findOrFail(999999);
        });

        $response = $this->getJson('/api/v1/__test/missing-model');

        $response->assertStatus(404);
        $this->assertFalse($response->json('success'));
        $this->assertSame('The requested resource was not found.', $response->json('message'));
        // Confirms the model class name isn't leaked.
        $this->assertStringNotContainsString('Client', $response->getContent());
    }

    public function test_conflict_returns_409_with_standard_envelope(): void
    {
        Route::middleware('api')->get('/api/v1/__test/conflict', function () {
            throw new ConflictHttpException('Resource already in that state.');
        });

        $response = $this->getJson('/api/v1/__test/conflict');

        $response->assertStatus(409);
        $this->assertFalse($response->json('success'));
        $this->assertSame('Resource already in that state.', $response->json('message'));
    }

    public function test_rate_limited_returns_429_with_standard_envelope(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => "user{$i}@example.com", 'password' => 'wrong', 'device_name' => 'x']);
        }

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'one-more@example.com', 'password' => 'wrong', 'device_name' => 'x']);

        $response->assertStatus(429)->assertJsonStructure(['success', 'message', 'errors']);
        $this->assertFalse($response->json('success'));
    }

    public function test_server_error_returns_500_with_generic_message_and_no_sensitive_details(): void
    {
        Route::middleware('api')->get('/api/v1/__test/boom', function () {
            throw new \RuntimeException('DB password is secret123 at /var/www/.env line 4');
        });

        $response = $this->getJson('/api/v1/__test/boom');

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
        $this->assertSame('Server error. Please try again later.', $response->json('message'));

        $body = $response->getContent();
        $this->assertStringNotContainsString('secret123', $body);
        $this->assertStringNotContainsString('.env', $body);
        $this->assertStringNotContainsString('RuntimeException', $body);
        $this->assertStringNotContainsString('#0 ', $body); // stack trace marker
        $this->assertStringNotContainsString('vendor/laravel', $body);
    }

    public function test_no_api_error_response_is_ever_html(): void
    {
        $responses = [
            $this->getJson('/api/v1/user'), // 401
            $this->getJson('/api/v1/nope-not-real'), // 404
            $this->postJson('/api/v1/auth/login', []), // 422
        ];

        foreach ($responses as $response) {
            $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
            $this->assertStringNotContainsString('<html', strtolower($response->getContent()));
        }
    }

    public function test_collection_helper_shapes_a_paginator_into_data_and_meta(): void
    {
        Client::factory()->count(3)->create();
        $paginator = Client::query()->paginate(2);

        $response = ApiResponse::collection($paginator, 'Clients retrieved successfully.');
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $this->assertSame('Clients retrieved successfully.', $payload['message']);
        $this->assertCount(2, $payload['data']);
        $this->assertSame(3, $payload['meta']['total']);
        $this->assertSame(1, $payload['meta']['current_page']);
        $this->assertSame(2, $payload['meta']['last_page']);
    }

    public function test_error_helper_defaults_errors_to_an_empty_object_when_none_given(): void
    {
        $response = ApiResponse::error('Something went wrong.');
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['success']);
        $this->assertSame([], $payload['errors']);
        $this->assertSame(400, $response->getStatusCode());
    }
}
