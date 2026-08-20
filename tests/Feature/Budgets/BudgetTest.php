<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

test('a guest cannot access budgets', function (): void {
    $this->getJson('/api/v1/budgets')->assertUnauthorized();
});

test('a user can create a budget for an expense category', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['type' => 'expense']);

    $response = $this->actingAs($user)->postJson('/api/v1/budgets', [
        'category_id' => $category->id,
        'amount' => 300,
        'period_month' => 3,
        'period_year' => 2026,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.amount', '300.00')
        ->assertJsonPath('data.spent', '0.00')
        ->assertJsonPath('data.remaining', '300.00')
        ->assertJsonPath('data.percentage_used', 0);
});

test('a budget cannot be created for an income category', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['type' => 'income']);

    $response = $this->actingAs($user)->postJson('/api/v1/budgets', [
        'category_id' => $category->id,
        'amount' => 300,
        'period_month' => 3,
        'period_year' => 2026,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('category_id');
});

test('a user cannot create two budgets for the same category and period', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['type' => 'expense']);
    Budget::factory()->create([
        'user_id' => $user->id, 'category_id' => $category->id, 'period_month' => 3, 'period_year' => 2026,
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/budgets', [
        'category_id' => $category->id,
        'amount' => 100,
        'period_month' => 3,
        'period_year' => 2026,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('category_id');
});

test('listing budgets computes spent, remaining, and percentage from that period\'s expenses', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create(['type' => 'expense']);
    Budget::factory()->create([
        'user_id' => $user->id, 'category_id' => $category->id, 'amount' => 200, 'period_month' => 3, 'period_year' => 2026,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id,
        'type' => 'expense', 'amount' => 50, 'transaction_date' => '2026-03-05',
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id,
        'type' => 'expense', 'amount' => 30, 'transaction_date' => '2026-03-20',
    ]);
    // Outside the period — must not count.
    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id,
        'type' => 'expense', 'amount' => 999, 'transaction_date' => '2026-04-01',
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/budgets?month=3&year=2026');

    $response->assertOk()
        ->assertJsonPath('data.0.spent', '80.00')
        ->assertJsonPath('data.0.remaining', '120.00')
        ->assertJsonPath('data.0.percentage_used', 40)
        ->assertJsonPath('data.0.is_over_budget', false);
});

test('a budget over its amount is flagged as over budget', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create(['type' => 'expense']);
    Budget::factory()->create([
        'user_id' => $user->id, 'category_id' => $category->id, 'amount' => 100, 'period_month' => 3, 'period_year' => 2026,
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id,
        'type' => 'expense', 'amount' => 150, 'transaction_date' => '2026-03-05',
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/budgets?month=3&year=2026');

    $response->assertOk()
        ->assertJsonPath('data.0.remaining', '-50.00')
        ->assertJsonPath('data.0.is_over_budget', true);
});

test('budgets only show for the requested month and year', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['type' => 'expense']);
    Budget::factory()->create(['user_id' => $user->id, 'category_id' => $category->id, 'period_month' => 3, 'period_year' => 2026]);
    Budget::factory()->create(['user_id' => $user->id, 'category_id' => $category->id, 'period_month' => 4, 'period_year' => 2026]);

    $response = $this->actingAs($user)->getJson('/api/v1/budgets?month=3&year=2026');

    $response->assertOk()->assertJsonCount(1, 'data');
});

test('a user can update their own budget', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['type' => 'expense']);
    $budget = Budget::factory()->create(['user_id' => $user->id, 'category_id' => $category->id, 'amount' => 100]);

    $response = $this->actingAs($user)->putJson("/api/v1/budgets/{$budget->id}", [
        'category_id' => $budget->category_id,
        'amount' => 250,
        'period_month' => $budget->period_month,
        'period_year' => $budget->period_year,
    ]);

    $response->assertOk()->assertJsonPath('data.amount', '250.00');
});

test('a user cannot update another user\'s budget', function (): void {
    $owner = User::factory()->create();
    $budget = Budget::factory()->create(['user_id' => $owner->id]);
    $other = User::factory()->create();

    $this->actingAs($other)->putJson("/api/v1/budgets/{$budget->id}", [
        'category_id' => $budget->category_id,
        'amount' => 999,
        'period_month' => $budget->period_month,
        'period_year' => $budget->period_year,
    ])->assertForbidden();
});

test('a user can delete their own budget', function (): void {
    $user = User::factory()->create();
    $budget = Budget::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->deleteJson("/api/v1/budgets/{$budget->id}")->assertNoContent();
    $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
});

test('a user cannot delete another user\'s budget', function (): void {
    $owner = User::factory()->create();
    $budget = Budget::factory()->create(['user_id' => $owner->id]);
    $other = User::factory()->create();

    $this->actingAs($other)->deleteJson("/api/v1/budgets/{$budget->id}")->assertForbidden();
    $this->assertDatabaseHas('budgets', ['id' => $budget->id]);
});
