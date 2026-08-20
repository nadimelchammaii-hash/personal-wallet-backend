<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

test('a user can log in with correct credentials', function (): void {
    $user = User::factory()->create(['password' => 'Password123!']);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'Password123!',
    ]);

    $response->assertOk()->assertJsonPath('data.email', $user->email);
    $this->assertAuthenticatedAs($user);
});

test('login fails with an incorrect password', function (): void {
    $user = User::factory()->create(['password' => 'Password123!']);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->assertGuest();
});

test('login is rate limited after repeated failures', function (): void {
    $user = User::factory()->create(['password' => 'Password123!']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'Password123!',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->assertGuest();

    RateLimiter::clear(Str::lower($user->email).'|127.0.0.1');
});

test('an authenticated user can fetch their own profile via me', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/me');

    $response->assertOk()->assertJsonPath('data.id', $user->id);
});

test('a guest cannot access me', function (): void {
    $response = $this->getJson('/api/v1/me');

    $response->assertUnauthorized();
});

test('an authenticated user can log out', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/logout');

    $response->assertOk();

    // Not assertGuest(): the auth:sanctum middleware that authenticated
    // this request switches the app's *default* guard to 'sanctum' for
    // the rest of the process, and that guard caches its resolved user
    // independently of 'web' (only relevant because tests share one PHP
    // process across requests). 'web' is the guard logout() actually
    // clears, and the one a fresh real request would resolve against.
    $this->assertGuest('web');
});
