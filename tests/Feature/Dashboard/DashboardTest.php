<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;

test('a guest cannot access the dashboard summary', function (): void {
    $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
});

test('the dashboard summary aggregates balance, income, expenses, and savings for the current month', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 500, 'current_balance' => 500]);
    $food = Category::factory()->for($user)->create(['type' => 'expense', 'name' => 'Food']);
    $salary = Category::factory()->for($user)->create(['type' => 'income', 'name' => 'Salary']);

    $now = now();
    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $salary->id,
        'type' => 'income', 'amount' => 2000, 'transaction_date' => $now->toDateString(),
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $food->id,
        'type' => 'expense', 'amount' => 300, 'transaction_date' => $now->toDateString(),
    ]);
    // Last month — must not count toward this month's totals.
    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $food->id,
        'type' => 'expense', 'amount' => 9999, 'transaction_date' => $now->copy()->subMonth()->toDateString(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard/summary');

    $response->assertOk()
        ->assertJsonPath('data.balance', '500.00')
        ->assertJsonPath('data.income', '2000.00')
        ->assertJsonPath('data.expenses', '300.00')
        ->assertJsonPath('data.savings', '1700.00');
});

test('archived accounts are excluded from the balance', function (): void {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['current_balance' => 100, 'is_archived' => false]);
    Account::factory()->for($user)->create(['current_balance' => 5000, 'is_archived' => true]);

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard/summary');

    $response->assertOk()->assertJsonPath('data.balance', '100.00');
});

test('remaining budget is null when no budgets exist for the current month', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard/summary');

    $response->assertOk()->assertJsonPath('data.remaining_budget', null);
});

test('remaining budget sums across all budgeted categories for the current month', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $food = Category::factory()->for($user)->create(['type' => 'expense']);
    $fun = Category::factory()->for($user)->create(['type' => 'expense']);
    $now = now();

    Budget::factory()->create([
        'user_id' => $user->id, 'category_id' => $food->id, 'amount' => 200,
        'period_month' => $now->month, 'period_year' => $now->year,
    ]);
    Budget::factory()->create([
        'user_id' => $user->id, 'category_id' => $fun->id, 'amount' => 100,
        'period_month' => $now->month, 'period_year' => $now->year,
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $food->id,
        'type' => 'expense', 'amount' => 50, 'transaction_date' => $now->toDateString(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard/summary');

    // budgeted 300 total, spent 50 total -> remaining 250
    $response->assertOk()->assertJsonPath('data.remaining_budget', '250.00');
});

test('spending by category groups this month\'s expenses by category', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $food = Category::factory()->for($user)->create(['type' => 'expense', 'name' => 'Food']);

    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $food->id,
        'type' => 'expense', 'amount' => 40, 'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $food->id,
        'type' => 'expense', 'amount' => 60, 'transaction_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard/summary');

    $response->assertOk()
        ->assertJsonPath('data.spending_by_category.0.category.name', 'Food')
        ->assertJsonPath('data.spending_by_category.0.amount', '100.00');
});

test('recent transactions and active goals are included', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create(['type' => 'expense']);
    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id,
        'type' => 'expense', 'amount' => 20, 'transaction_date' => now()->toDateString(),
    ]);
    SavingsGoal::factory()->for($user)->create(['status' => 'active']);
    SavingsGoal::factory()->for($user)->create(['status' => 'archived']);

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard/summary');

    $response->assertOk()
        ->assertJsonCount(1, 'data.recent_transactions')
        ->assertJsonCount(1, 'data.goals');
});
