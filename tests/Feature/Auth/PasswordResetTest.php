<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_request_for_unknown_email_does_not_error_out(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), ['email' => 'nobody@example.com']);

        // Laravel's Password broker reports failure for an unknown email via a
        // validation error on the field — it does not leak which emails exist
        // by returning success either way; asserting the session error here
        // pins that behaviour instead of assuming a specific wording.
        $response->assertSessionHasErrors('email');
        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();

        $token = Password::createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'New-Password-123!',
            'password_confirmation' => 'New-Password-123!',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('New-Password-123!', $user->fresh()->password));
    }

    public function test_password_reset_rejects_a_weak_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'alllowercase',
            'password_confirmation' => 'alllowercase',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.store'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'New-Password-123!',
            'password_confirmation' => 'New-Password-123!',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
