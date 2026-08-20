<?php

use App\Models\Account;
use App\Models\User;

test('a guest cannot access accounts', function (): void {
    $this->getJson('/api/v1/accounts')->assertUnauthorized();
});

test('a user only sees their own active accounts by default', function (): void {
    $user = User::factory()->create();
    Account::factory()->for($user)->count(2)->create();
    Account::factory()->for($user)->archived()->create();
    Account::factory()->count(3)->create(); // someone else's

    $response = $this->actingAs($user)->getJson('/api/v1/accounts');

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('archived accounts are included when requested', function (): void {
    $user = User::factory()->create();
    Account::factory()->for($user)->create();
    Account::factory()->for($user)->archived()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/accounts?include_archived=1');

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('a user can create an account, and current_balance mirrors initial_balance', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/accounts', [
        'name' => 'Checking',
        'type' => 'bank',
        'currency' => 'USD',
        'initial_balance' => 1250.5,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Checking')
        ->assertJsonPath('data.current_balance', '1250.50')
        ->assertJsonPath('data.icon', 'mdi-bank');

    $this->assertDatabaseHas('accounts', ['user_id' => $user->id, 'name' => 'Checking']);
});

test('creating an account requires a valid type', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/accounts', [
        'name' => 'Checking',
        'type' => 'not-a-type',
        'currency' => 'USD',
        'initial_balance' => 100,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('type');
});

test('a user can view their own account', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $response = $this->actingAs($user)->getJson("/api/v1/accounts/{$account->id}");

    $response->assertOk()->assertJsonPath('data.id', $account->id);
});

test('a user cannot view another user\'s account', function (): void {
    $owner = User::factory()->create();
    $account = Account::factory()->for($owner)->create();
    $other = User::factory()->create();

    $this->actingAs($other)->getJson("/api/v1/accounts/{$account->id}")->assertForbidden();
});

test('a user can update their own account', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 100]);

    $response = $this->actingAs($user)->putJson("/api/v1/accounts/{$account->id}", [
        'name' => 'Renamed',
        'type' => $account->type,
        'currency' => $account->currency,
        'initial_balance' => 200,
        'is_archived' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.current_balance', '200.00')
        ->assertJsonPath('data.is_archived', true);
});

test('a user cannot update another user\'s account', function (): void {
    $owner = User::factory()->create();
    $account = Account::factory()->for($owner)->create();
    $other = User::factory()->create();

    $this->actingAs($other)->putJson("/api/v1/accounts/{$account->id}", [
        'name' => 'Hijacked',
        'type' => $account->type,
        'currency' => $account->currency,
        'initial_balance' => 0,
    ])->assertForbidden();
});

test('a user can delete their own account', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->deleteJson("/api/v1/accounts/{$account->id}")->assertNoContent();

    $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
});

test('a user cannot delete another user\'s account', function (): void {
    $owner = User::factory()->create();
    $account = Account::factory()->for($owner)->create();
    $other = User::factory()->create();

    $this->actingAs($other)->deleteJson("/api/v1/accounts/{$account->id}")->assertForbidden();

    $this->assertDatabaseHas('accounts', ['id' => $account->id]);
});
