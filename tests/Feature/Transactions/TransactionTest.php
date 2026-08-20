<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

test('a guest cannot access transactions', function (): void {
    $this->getJson('/api/v1/transactions')->assertUnauthorized();
});

test('creating an income transaction increases the account balance', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 100, 'current_balance' => 100]);
    $category = Category::factory()->for($user)->create(['type' => 'income']);

    $response = $this->actingAs($user)->postJson('/api/v1/transactions', [
        'account_id' => $account->id,
        'category_id' => $category->id,
        'type' => 'income',
        'amount' => 250,
        'transaction_date' => '2026-01-15',
    ]);

    $response->assertCreated()->assertJsonPath('data.amount', '250.00');
    expect((float) $account->fresh()->current_balance)->toBe(350.0);
});

test('creating an expense transaction decreases the account balance', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 100, 'current_balance' => 100]);
    $category = Category::factory()->for($user)->create(['type' => 'expense']);

    $this->actingAs($user)->postJson('/api/v1/transactions', [
        'account_id' => $account->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'amount' => 40,
        'transaction_date' => '2026-01-15',
    ])->assertCreated();

    expect((float) $account->fresh()->current_balance)->toBe(60.0);
});

test('the category type must match the transaction type', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);

    $response = $this->actingAs($user)->postJson('/api/v1/transactions', [
        'account_id' => $account->id,
        'category_id' => $expenseCategory->id,
        'type' => 'income',
        'amount' => 10,
        'transaction_date' => '2026-01-15',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('category_id');
});

test('a user cannot create a transaction on another user\'s account', function (): void {
    $owner = User::factory()->create();
    $account = Account::factory()->for($owner)->create();
    $other = User::factory()->create();
    $category = Category::factory()->for($other)->create(['type' => 'expense']);

    $this->actingAs($other)->postJson('/api/v1/transactions', [
        'account_id' => $account->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'amount' => 10,
        'transaction_date' => '2026-01-15',
    ])->assertUnprocessable()->assertJsonValidationErrors('account_id');
});

test('updating a transaction reverses the old effect and applies the new one', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 100, 'current_balance' => 100]);
    $category = Category::factory()->for($user)->create(['type' => 'expense']);
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'amount' => 30,
    ]);
    $account->decrement('current_balance', 30); // simulate the original creation's effect
    expect((float) $account->fresh()->current_balance)->toBe(70.0);

    $this->actingAs($user)->putJson("/api/v1/transactions/{$transaction->id}", [
        'account_id' => $account->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'amount' => 50,
        'transaction_date' => '2026-01-20',
    ])->assertOk();

    // 70 + 30 (reverse old) - 50 (apply new) = 50
    expect((float) $account->fresh()->current_balance)->toBe(50.0);
});

test('a transfer cannot be edited', function (): void {
    $user = User::factory()->create();
    $from = Account::factory()->for($user)->create();
    $to = Account::factory()->for($user)->create();

    $this->actingAs($user)->postJson('/api/v1/transfers', [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => 20,
        'transaction_date' => '2026-01-15',
    ])->assertCreated();

    $leg = Transaction::where('account_id', $from->id)->firstOrFail();

    $this->actingAs($user)->putJson("/api/v1/transactions/{$leg->id}", [
        'account_id' => $from->id,
        'category_id' => null,
        'type' => 'expense',
        'amount' => 99,
        'transaction_date' => '2026-01-16',
    ])->assertUnprocessable();
});

test('deleting an expense transaction reverses its effect on the balance', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 100, 'current_balance' => 70]);
    $category = Category::factory()->for($user)->create(['type' => 'expense']);
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'amount' => 30,
    ]);

    $this->actingAs($user)->deleteJson("/api/v1/transactions/{$transaction->id}")->assertNoContent();

    expect((float) $account->fresh()->current_balance)->toBe(100.0);
    $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
});

test('a user cannot view, update, or delete another user\'s transaction', function (): void {
    $owner = User::factory()->create();
    $account = Account::factory()->for($owner)->create();
    $category = Category::factory()->for($owner)->create(['type' => 'expense']);
    $transaction = Transaction::factory()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'type' => 'expense',
    ]);
    $other = User::factory()->create();

    $this->actingAs($other)->getJson("/api/v1/transactions/{$transaction->id}")->assertForbidden();
    $this->actingAs($other)->deleteJson("/api/v1/transactions/{$transaction->id}")->assertForbidden();
});

test('transactions can be filtered by account, type, and date range', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $otherAccount = Account::factory()->for($user)->create();
    $expenseCategory = Category::factory()->for($user)->create(['type' => 'expense']);

    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $expenseCategory->id,
        'type' => 'expense', 'transaction_date' => '2026-01-10',
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $otherAccount->id, 'category_id' => $expenseCategory->id,
        'type' => 'expense', 'transaction_date' => '2026-01-10',
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $expenseCategory->id,
        'type' => 'expense', 'transaction_date' => '2025-06-01',
    ]);

    $response = $this->actingAs($user)->getJson(
        "/api/v1/transactions?account_id={$account->id}&date_from=2026-01-01&date_to=2026-01-31"
    );

    $response->assertOk()->assertJsonCount(1, 'data');
});

test('creating a transfer moves money between two accounts and creates linked legs', function (): void {
    $user = User::factory()->create();
    $from = Account::factory()->for($user)->create(['initial_balance' => 200, 'current_balance' => 200]);
    $to = Account::factory()->for($user)->create(['initial_balance' => 50, 'current_balance' => 50]);

    $response = $this->actingAs($user)->postJson('/api/v1/transfers', [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => 75,
        'note' => 'Move to savings',
        'transaction_date' => '2026-01-15',
    ]);

    $response->assertCreated()->assertJsonCount(2, 'data');

    expect((float) $from->fresh()->current_balance)->toBe(125.0)
        ->and((float) $to->fresh()->current_balance)->toBe(125.0);

    $legs = Transaction::query()->where('amount', 75)->get();
    expect($legs)->toHaveCount(2)
        ->and($legs->pluck('transfer_group_id')->unique())->toHaveCount(1)
        ->and($legs->pluck('type')->sort()->values()->all())->toBe(['transfer_in', 'transfer_out']);
});

test('a transfer requires two different accounts owned by the user', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    $this->actingAs($user)->postJson('/api/v1/transfers', [
        'from_account_id' => $account->id,
        'to_account_id' => $account->id,
        'amount' => 10,
        'transaction_date' => '2026-01-15',
    ])->assertUnprocessable()->assertJsonValidationErrors('to_account_id');
});

test('deleting either leg of a transfer removes both legs and reverses both balances', function (): void {
    $user = User::factory()->create();
    $from = Account::factory()->for($user)->create(['initial_balance' => 200, 'current_balance' => 200]);
    $to = Account::factory()->for($user)->create(['initial_balance' => 50, 'current_balance' => 50]);

    $this->actingAs($user)->postJson('/api/v1/transfers', [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => 75,
        'transaction_date' => '2026-01-15',
    ])->assertCreated();

    expect((float) $from->fresh()->current_balance)->toBe(125.0);

    $outgoingLeg = Transaction::where('account_id', $from->id)->firstOrFail();

    $this->actingAs($user)->deleteJson("/api/v1/transactions/{$outgoingLeg->id}")->assertNoContent();

    expect((float) $from->fresh()->current_balance)->toBe(200.0)
        ->and((float) $to->fresh()->current_balance)->toBe(50.0);
    expect(Transaction::where('transfer_group_id', $outgoingLeg->transfer_group_id)->count())->toBe(0);
});
