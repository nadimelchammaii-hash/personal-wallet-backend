<?php

use App\Models\SavingsGoal;
use App\Models\User;

test('a guest cannot access goals', function (): void {
    $this->getJson('/api/v1/goals')->assertUnauthorized();
});

test('a user can create a savings goal', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/goals', [
        'name' => 'New Laptop',
        'target_amount' => 1500,
        'target_date' => '2026-12-31',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'New Laptop')
        ->assertJsonPath('data.target_amount', '1500.00')
        ->assertJsonPath('data.current_amount', '0.00')
        ->assertJsonPath('data.remaining_amount', '1500.00')
        ->assertJsonPath('data.progress_percentage', 0)
        ->assertJsonPath('data.status', 'active');
});

test('a user only sees their own goals', function (): void {
    $user = User::factory()->create();
    SavingsGoal::factory()->for($user)->count(2)->create();
    SavingsGoal::factory()->count(3)->create(); // someone else's

    $response = $this->actingAs($user)->getJson('/api/v1/goals');

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('a user can update their own goal', function (): void {
    $user = User::factory()->create();
    $goal = SavingsGoal::factory()->for($user)->create(['name' => 'Old name']);

    $response = $this->actingAs($user)->putJson("/api/v1/goals/{$goal->id}", [
        'name' => 'New name',
        'target_amount' => $goal->target_amount,
    ]);

    $response->assertOk()->assertJsonPath('data.name', 'New name');
});

test('a user cannot update another user\'s goal', function (): void {
    $owner = User::factory()->create();
    $goal = SavingsGoal::factory()->for($owner)->create();
    $other = User::factory()->create();

    $this->actingAs($other)->putJson("/api/v1/goals/{$goal->id}", [
        'name' => 'Hijacked',
        'target_amount' => $goal->target_amount,
    ])->assertForbidden();
});

test('a user can delete their own goal', function (): void {
    $user = User::factory()->create();
    $goal = SavingsGoal::factory()->for($user)->create();

    $this->actingAs($user)->deleteJson("/api/v1/goals/{$goal->id}")->assertNoContent();
    $this->assertDatabaseMissing('savings_goals', ['id' => $goal->id]);
});

test('a user cannot delete another user\'s goal', function (): void {
    $owner = User::factory()->create();
    $goal = SavingsGoal::factory()->for($owner)->create();
    $other = User::factory()->create();

    $this->actingAs($other)->deleteJson("/api/v1/goals/{$goal->id}")->assertForbidden();
    $this->assertDatabaseHas('savings_goals', ['id' => $goal->id]);
});

test('adding a contribution increases the goal\'s current amount and progress', function (): void {
    $user = User::factory()->create();
    $goal = SavingsGoal::factory()->for($user)->create(['target_amount' => 1000, 'current_amount' => 0]);

    $response = $this->actingAs($user)->postJson("/api/v1/goals/{$goal->id}/contributions", [
        'amount' => 250,
        'contributed_at' => '2026-01-15',
        'note' => 'Birthday money',
    ]);

    $response->assertCreated()->assertJsonPath('data.amount', '250.00');
    expect((float) $goal->fresh()->current_amount)->toBe(250.0);

    $goalResponse = $this->actingAs($user)->getJson('/api/v1/goals');
    $goalResponse->assertJsonPath('data.0.progress_percentage', 25)
        ->assertJsonPath('data.0.remaining_amount', '750.00');
});

test('a goal is automatically marked completed once fully funded', function (): void {
    $user = User::factory()->create();
    $goal = SavingsGoal::factory()->for($user)->create(['target_amount' => 100, 'current_amount' => 0, 'status' => 'active']);

    $this->actingAs($user)->postJson("/api/v1/goals/{$goal->id}/contributions", [
        'amount' => 120,
        'contributed_at' => '2026-01-15',
    ])->assertCreated();

    expect($goal->fresh()->status)->toBe('completed');
});

test('a user cannot contribute to another user\'s goal', function (): void {
    $owner = User::factory()->create();
    $goal = SavingsGoal::factory()->for($owner)->create();
    $other = User::factory()->create();

    $this->actingAs($other)->postJson("/api/v1/goals/{$goal->id}/contributions", [
        'amount' => 50,
        'contributed_at' => '2026-01-15',
    ])->assertForbidden();
});

test('contributions are listed most recent first', function (): void {
    $user = User::factory()->create();
    $goal = SavingsGoal::factory()->for($user)->create();

    $this->actingAs($user)->postJson("/api/v1/goals/{$goal->id}/contributions", [
        'amount' => 50, 'contributed_at' => '2026-01-01',
    ])->assertCreated();
    $this->actingAs($user)->postJson("/api/v1/goals/{$goal->id}/contributions", [
        'amount' => 75, 'contributed_at' => '2026-02-01',
    ])->assertCreated();

    $response = $this->actingAs($user)->getJson("/api/v1/goals/{$goal->id}/contributions");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.amount', '75.00')
        ->assertJsonPath('data.1.amount', '50.00');
});
