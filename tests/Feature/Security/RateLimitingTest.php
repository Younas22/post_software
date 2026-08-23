<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    // No manual rate-limiter reset needed between tests: Laravel's base
    // TestCase boots a fresh Application (and so a fresh in-memory 'array'
    // cache, which is what these routes' throttle middleware is backed by
    // in the test environment) for every test method.

    public function test_login_route_is_throttled_by_ip_across_different_emails(): void
    {
        // 10 different emails from one IP — LoginRequest's own per-email
        // limiter (5 attempts) never trips since each email is only tried
        // once, isolating this test to the route-level throttle:10,1.
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('login'), ['email' => "user{$i}@example.com", 'password' => 'wrong']);
        }

        $response = $this->post(route('login'), ['email' => 'user10@example.com', 'password' => 'wrong']);

        $response->assertStatus(429);
    }

    public function test_forgot_password_route_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => "user{$i}@example.com"]);
        }

        $response = $this->post(route('password.email'), ['email' => 'one-more@example.com']);

        $response->assertStatus(429);
    }

    public function test_login_requests_within_the_limit_are_not_throttled(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password']);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
    }
}
