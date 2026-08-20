<?php

use App\Models\User;

test('an authenticated user can update their profile', function (): void {
    $user = User::factory()->create(['currency' => 'USD', 'timezone' => 'UTC']);

    $response = $this->actingAs($user)->putJson('/api/v1/me', [
        'name' => 'New Name',
        'email' => $user->email,
        'currency' => 'EUR',
        'timezone' => 'Europe/Paris',
    ]);

    $response->assertOk()->assertJsonPath('data.currency', 'EUR');
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name', 'currency' => 'EUR']);
});

test('updating a profile rejects an email already taken by another user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $response = $this->actingAs($user)->putJson('/api/v1/me', [
        'name' => $user->name,
        'email' => $other->email,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('a user keeping their own email on update is not rejected as a duplicate', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson('/api/v1/me', [
        'name' => 'Updated Name',
        'email' => $user->email,
        'currency' => 'USD',
        'timezone' => 'UTC',
    ]);

    $response->assertOk();
});

test('an authenticated user can change their password with the correct current password', function (): void {
    $user = User::factory()->create(['password' => 'OldPassword123!']);
    $originalHash = $user->password;

    // actingAs() hands the guard this exact $user instance, and the
    // controller mutates it via $request->user(), so $originalHash has
    // to be captured before the request rather than read off $user after.
    $response = $this->actingAs($user)->putJson('/api/v1/me/password', [
        'current_password' => 'OldPassword123!',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertOk();
    expect($user->fresh()->password)->not->toBe($originalHash);
});

test('changing a password fails with the wrong current password', function (): void {
    $user = User::factory()->create(['password' => 'OldPassword123!']);

    $response = $this->actingAs($user)->putJson('/api/v1/me/password', [
        'current_password' => 'wrong-password',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('current_password');
});
