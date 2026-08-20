<?php

use App\Models\User;

test('a user can register with valid details', function (): void {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertCreated()->assertJsonPath('data.email', 'ada@example.com');

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);

    $user = User::whereEmail('ada@example.com')->firstOrFail();
    expect($user->currency)->toBe('USD')
        ->and($user->timezone)->toBe('UTC')
        ->and($user->password)->not->toBe('Password123!');
});

test('registration requires matching password confirmation', function (): void {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'something-else',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->assertGuest();
});

test('registration rejects a duplicate email', function (): void {
    User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->postJson('/api/v1/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});
