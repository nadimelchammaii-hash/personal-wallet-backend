<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('forgot-password dispatches a reset link for an existing user', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/forgot-password', ['email' => $user->email]);

    $response->assertOk();
    Notification::assertSentTo($user, ResetPassword::class);
});

test('forgot-password responds the same generic message for an unknown email, to avoid enumeration', function (): void {
    Notification::fake();

    $response = $this->postJson('/api/v1/forgot-password', ['email' => 'nobody@example.com']);

    $response->assertOk()->assertJsonPath('message', 'If an account exists for that email, a reset link has been sent.');
    Notification::assertNothingSent();
});

test('a user can reset their password with a valid token', function (): void {
    $user = User::factory()->create(['password' => 'OldPassword123!']);
    $token = Password::createToken($user);

    $response = $this->postJson('/api/v1/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertOk();

    $login = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'NewPassword123!',
    ]);
    $login->assertOk();
});

test('resetting a password fails with an invalid token', function (): void {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/reset-password', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});
