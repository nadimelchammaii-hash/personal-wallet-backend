<?php

namespace App\Services;

use App\Models\Category;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Populates a freshly-created demo account with a few months of realistic
 * activity, so a visitor lands on a populated Dashboard/Reports instead of
 * empty states. Transactions are written through TransactionService so each
 * account's current_balance stays correct, exactly as it would for a real user.
 */
class DemoDataSeeder
{
    public function __construct(protected TransactionService $transactions) {}

    public function seed(User $user): void
    {
        DB::transaction(function () use ($user) {
            [$checking, $savings, $creditCard] = $this->createAccounts($user);
            $this->createTransactions($user, $checking, $creditCard);
            $this->createBudgets($user);
            $this->createGoals($user);
        });
    }

    /** @return array{0: \App\Models\Account, 1: \App\Models\Account, 2: \App\Models\Account} */
    protected function createAccounts(User $user): array
    {
        $checking = $user->accounts()->create([
            'name' => 'Checking', 'type' => 'bank', 'currency' => 'USD',
            'initial_balance' => 1800, 'current_balance' => 1800, 'icon' => 'mdi-bank',
        ]);

        $savings = $user->accounts()->create([
            'name' => 'Savings', 'type' => 'savings', 'currency' => 'USD',
            'initial_balance' => 6000, 'current_balance' => 6000, 'icon' => 'mdi-piggy-bank',
        ]);

        $creditCard = $user->accounts()->create([
            'name' => 'Credit Card', 'type' => 'credit_card', 'currency' => 'USD',
            'initial_balance' => -350, 'current_balance' => -350, 'icon' => 'mdi-credit-card',
        ]);

        return [$checking, $savings, $creditCard];
    }

    protected function createTransactions(User $user, $checking, $creditCard): void
    {
        $salary = $this->category('Salary', 'income');
        $food = $this->category('Food', 'expense');
        $transportation = $this->category('Transportation', 'expense');
        $shopping = $this->category('Shopping', 'expense');
        $bills = $this->category('Bills', 'expense');
        $entertainment = $this->category('Entertainment', 'expense');
        $health = $this->category('Health', 'expense');

        $now = Carbon::now();

        // Three months of history, oldest first, so the trends chart has shape.
        foreach ([2, 1, 0] as $monthsAgo) {
            $month = $now->copy()->subMonths($monthsAgo);
            $isCurrentMonth = $monthsAgo === 0;

            $this->transaction($user, $checking, 'income', $salary, 3200, $month->copy()->startOfMonth()->addDays(1), 'Monthly salary');

            // Current month is tuned so Food lands over its $300 budget,
            // Transportation stays comfortably under, Entertainment sits near its limit.
            $foodSpend = $isCurrentMonth ? [120, 95, 110] : [90, 70, 85];
            foreach ($foodSpend as $i => $amount) {
                $this->transaction($user, $checking, 'expense', $food, $amount, $month->copy()->startOfMonth()->addDays(3 + $i * 7), 'Groceries');
            }

            $transportSpend = $isCurrentMonth ? [45, 38] : [40, 35];
            foreach ($transportSpend as $i => $amount) {
                $this->transaction($user, $checking, 'expense', $transportation, $amount, $month->copy()->startOfMonth()->addDays(5 + $i * 9), 'Gas / transit');
            }

            $this->transaction($user, $checking, 'expense', $bills, 165, $month->copy()->startOfMonth()->addDays(2), 'Electricity & internet');

            $entertainmentSpend = $isCurrentMonth ? [55, 35] : [40, 25];
            foreach ($entertainmentSpend as $i => $amount) {
                $this->transaction($user, $creditCard, 'expense', $entertainment, $amount, $month->copy()->startOfMonth()->addDays(10 + $i * 8), 'Streaming / nights out');
            }

            $this->transaction($user, $creditCard, 'expense', $shopping, $isCurrentMonth ? 60 : 85, $month->copy()->startOfMonth()->addDays(14), 'Clothing');

            if ($monthsAgo === 1) {
                $this->transaction($user, $checking, 'expense', $health, 75, $month->copy()->startOfMonth()->addDays(20), 'Pharmacy');
            }
        }
    }

    protected function createBudgets(User $user): void
    {
        $now = Carbon::now();

        $budgets = [
            ['category' => 'Food', 'amount' => 300],
            ['category' => 'Transportation', 'amount' => 200],
            ['category' => 'Entertainment', 'amount' => 100],
        ];

        foreach ($budgets as $budget) {
            $user->budgets()->create([
                'category_id' => $this->category($budget['category'], 'expense')->id,
                'amount' => $budget['amount'],
                'period_month' => $now->month,
                'period_year' => $now->year,
            ]);
        }
    }

    protected function createGoals(User $user): void
    {
        $goals = [
            ['name' => 'Emergency Fund', 'target' => 5000, 'contributions' => [1500, 700], 'icon' => 'mdi-shield-check', 'color' => '#1E88E5', 'months_out' => 8],
            ['name' => 'Vacation to Japan', 'target' => 3000, 'contributions' => [450], 'icon' => 'mdi-airplane', 'color' => '#8E24AA', 'months_out' => 10],
            ['name' => 'New Laptop', 'target' => 1500, 'contributions' => [900, 600], 'icon' => 'mdi-laptop', 'color' => '#43A047', 'months_out' => 2],
        ];

        foreach ($goals as $goalData) {
            /** @var SavingsGoal $goal */
            $goal = $user->savingsGoals()->create([
                'name' => $goalData['name'],
                'target_amount' => $goalData['target'],
                'current_amount' => 0,
                'target_date' => Carbon::now()->addMonths($goalData['months_out'])->format('Y-m-d'),
                'icon' => $goalData['icon'],
                'color' => $goalData['color'],
                'status' => 'active',
            ]);

            foreach ($goalData['contributions'] as $i => $amount) {
                $goal->contributions()->create([
                    'amount' => $amount,
                    'contributed_at' => Carbon::now()->subWeeks(count($goalData['contributions']) - $i)->format('Y-m-d'),
                ]);
                $goal->increment('current_amount', $amount);
            }

            if ($goal->fresh()->current_amount >= $goal->target_amount) {
                $goal->update(['status' => 'completed']);
            }
        }
    }

    protected function transaction(User $user, $account, string $type, Category $category, float $amount, Carbon $date, string $note): void
    {
        $this->transactions->create($user, [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => $type,
            'amount' => $amount,
            'note' => $note,
            'transaction_date' => $date->format('Y-m-d'),
        ]);
    }

    protected function category(string $name, string $type): Category
    {
        return Category::query()->whereNull('user_id')->where('name', $name)->where('type', $type)->firstOrFail();
    }
}
