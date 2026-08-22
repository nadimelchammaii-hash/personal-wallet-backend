<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

test('a guest cannot access any report endpoint', function (): void {
    $this->getJson('/api/v1/reports/spending-trends')->assertUnauthorized();
    $this->getJson('/api/v1/reports/category-breakdown')->assertUnauthorized();
    $this->getJson('/api/v1/reports/budget-performance')->assertUnauthorized();
});

test('spending trends groups income and expenses by month for the requested range', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $salary = Category::factory()->for($user)->create(['type' => 'income']);
    $food = Category::factory()->for($user)->create(['type' => 'expense']);
    $now = now();

    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $salary->id,
        'type' => 'income', 'amount' => 1000, 'transaction_date' => $now->toDateString(),
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $food->id,
        'type' => 'expense', 'amount' => 200, 'transaction_date' => $now->toDateString(),
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $salary->id,
        'type' => 'income', 'amount' => 900, 'transaction_date' => $now->copy()->subMonth()->toDateString(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/reports/spending-trends?months=3');

    $response->assertOk()->assertJsonCount(3, 'data');

    $lastPeriod = $now->format('Y-m');
    $previousPeriod = $now->copy()->subMonth()->format('Y-m');

    $data = collect($response->json('data'))->keyBy('period');

    expect($data[$lastPeriod]['income'])->toBe('1000.00')
        ->and($data[$lastPeriod]['expenses'])->toBe('200.00')
        ->and($data[$previousPeriod]['income'])->toBe('900.00')
        ->and($data[$previousPeriod]['expenses'])->toBe('0.00');
});

test('category breakdown groups this month\'s expenses by category', function (): void {
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

    $response = $this->actingAs($user)->getJson('/api/v1/reports/category-breakdown');

    $response->assertOk()
        ->assertJsonPath('data.0.category.name', 'Food')
        ->assertJsonPath('data.0.amount', '100.00');
});

test('budget performance compares budgeted vs. spent per category for the requested period', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $food = Category::factory()->for($user)->create(['type' => 'expense', 'name' => 'Food']);
    $now = now();

    Budget::factory()->create([
        'user_id' => $user->id, 'category_id' => $food->id, 'amount' => 200,
        'period_month' => $now->month, 'period_year' => $now->year,
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $food->id,
        'type' => 'expense', 'amount' => 75, 'transaction_date' => $now->toDateString(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/reports/budget-performance');

    $response->assertOk()
        ->assertJsonPath('data.0.category.name', 'Food')
        ->assertJsonPath('data.0.budgeted', '200.00')
        ->assertJsonPath('data.0.spent', '75.00');
});

test('budget performance returns an empty list when no budgets exist for the period', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/reports/budget-performance');

    $response->assertOk()->assertJsonCount(0, 'data');
});
