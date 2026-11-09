<?php

namespace App\Services;

use App\Models\Category;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Populates a freshly-created demo account with ~5 months of realistic
 * activity, so a visitor lands on a populated Dashboard/Reports instead of
 * empty states. Every date is generated relative to now() and capped at
 * "today" for the current month — never in the future, since the demo can
 * be started on any day of the month. Transactions are written through
 * TransactionService so each account's current_balance stays correct,
 * exactly as it would for a real user.
 */
class DemoDataSeeder
{
    public function __construct(protected TransactionService $transactions) {}

    public function seed(User $user): void
    {
        DB::transaction(function () use ($user) {
            [$checking, $savings, $creditCard] = $this->createAccounts($user);
            $this->createTransactions($user, $checking, $savings, $creditCard);
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
            'initial_balance' => 4500, 'current_balance' => 4500, 'icon' => 'mdi-piggy-bank',
        ]);

        $creditCard = $user->accounts()->create([
            'name' => 'Credit Card', 'type' => 'credit_card', 'currency' => 'USD',
            'initial_balance' => -350, 'current_balance' => -350, 'icon' => 'mdi-credit-card',
        ]);

        return [$checking, $savings, $creditCard];
    }

    protected function createTransactions(User $user, $checking, $savings, $creditCard): void
    {
        $salary = $this->category('Salary', 'income');
        $food = $this->category('Food', 'expense');
        $transportation = $this->category('Transportation', 'expense');
        $shopping = $this->category('Shopping', 'expense');
        $bills = $this->category('Bills', 'expense');
        $entertainment = $this->category('Entertainment', 'expense');
        $health = $this->category('Health', 'expense');
        $education = $this->category('Education', 'expense');
        $other = $this->category('Other', 'expense');

        $now = Carbon::now();

        // ~5 months of history, oldest first. The current month (monthsAgo 0)
        // only seeds days up to and including today, since the demo can be
        // started on any day of the month and seeded data must never be
        // dated in the future.
        foreach ([4, 3, 2, 1, 0] as $monthsAgo) {
            $monthStart = $now->copy()->subMonths($monthsAgo)->startOfMonth();
            $isCurrentMonth = $monthsAgo === 0;
            $lastDay = $isCurrentMonth ? $now->day : $monthStart->daysInMonth;

            // Returns the date for a given day-of-month, or null if that day
            // hasn't happened yet this month (only possible for the current month).
            $seedDay = fn (int $day): ?Carbon => $day <= $lastDay ? $monthStart->copy()->addDays($day - 1) : null;

            // Salary — the 2nd of every month, like clockwork.
            if ($date = $seedDay(2)) {
                $this->transaction($user, $checking, 'income', $salary, 3200, $date, 'Monthly salary');
            }

            // Rent & utilities — fixed, the 3rd of every month.
            if ($date = $seedDay(3)) {
                $this->transaction($user, $checking, 'expense', $bills, 1250, $date, 'Rent & utilities');
            }

            // Groceries. The current month is front-loaded into the first
            // week and a half so the Food budget reliably shows "over" from
            // early in the month on, rather than depending on how late into
            // the month the demo happens to be started.
            $groceryDays = $isCurrentMonth ? [2, 5, 8] : [4, 11, 18, 25];
            foreach ($groceryDays as $i => $day) {
                if (! $date = $seedDay($day)) continue;
                $amount = $isCurrentMonth ? [120, 95, 110][$i] : fake()->randomFloat(2, 65, 95);
                $this->transaction($user, $checking, 'expense', $food, $amount, $date, 'Groceries');
            }

            // Transportation — a couple of fill-ups / transit passes a month.
            $transportDays = $isCurrentMonth ? [6] : [7, 16, 25];
            foreach ($transportDays as $day) {
                if (! $date = $seedDay($day)) continue;
                $this->transaction($user, $checking, 'expense', $transportation, fake()->randomFloat(2, 35, 55), $date, 'Gas / transit');
            }

            // Entertainment — streaming + the occasional night out.
            $entertainmentDays = $isCurrentMonth ? [9] : [10, 20];
            foreach ($entertainmentDays as $day) {
                if (! $date = $seedDay($day)) continue;
                $this->transaction($user, $creditCard, 'expense', $entertainment, fake()->randomFloat(2, 25, 60), $date, 'Streaming / nights out');
            }

            // Shopping — one discretionary purchase most months.
            if ($date = $seedDay(14)) {
                $this->transaction($user, $creditCard, 'expense', $shopping, fake()->randomFloat(2, 40, 140), $date, 'Clothing / home goods');
            }

            // A few irregular, one-off expenses scattered across the
            // history so the category mix looks lived-in, not templated.
            if ($monthsAgo === 3 && ($date = $seedDay(21))) {
                $this->transaction($user, $checking, 'expense', $health, 85, $date, 'Pharmacy');
            }
            if ($monthsAgo === 2 && ($date = $seedDay(17))) {
                $this->transaction($user, $creditCard, 'expense', $other, 220, $date, 'Car repair');
            }
            if ($monthsAgo === 1 && ($date = $seedDay(12))) {
                $this->transaction($user, $checking, 'expense', $education, 150, $date, 'Online course');
            }
        }

        // A couple of transfers into savings — always safely in the past.
        $this->transactions->createTransfer($user, [
            'from_account_id' => $checking->id,
            'to_account_id' => $savings->id,
            'amount' => 400,
            'note' => 'Moved to savings',
            'transaction_date' => $now->copy()->subMonths(3)->startOfMonth()->addDays(4)->format('Y-m-d'),
        ]);
        $this->transactions->createTransfer($user, [
            'from_account_id' => $checking->id,
            'to_account_id' => $savings->id,
            'amount' => 350,
            'note' => 'Moved to savings',
            'transaction_date' => $now->copy()->subMonths(1)->startOfMonth()->addDays(4)->format('Y-m-d'),
        ]);
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
        // Contributions are spread across the same ~4-month window as the
        // transaction history, so "progress over time" actually shows a
        // history rather than a couple of lump sums from last week.
        // now()->subWeeks(...) is always <= now(), so these never need the
        // future-date guard that month-anchored transaction dates do.
        $goals = [
            ['name' => 'Emergency Fund', 'target' => 5000, 'contributions' => [1200, 600, 400], 'weeks_ago' => [16, 9, 3], 'icon' => 'mdi-shield-check', 'color' => '#1E88E5', 'months_out' => 8],
            ['name' => 'Vacation to Japan', 'target' => 3000, 'contributions' => [250, 300, 150], 'weeks_ago' => [14, 7, 2], 'icon' => 'mdi-airplane', 'color' => '#8E24AA', 'months_out' => 10],
            ['name' => 'New Laptop', 'target' => 1500, 'contributions' => [600, 500, 400], 'weeks_ago' => [12, 6, 1], 'icon' => 'mdi-laptop', 'color' => '#43A047', 'months_out' => 2],
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
                    'contributed_at' => Carbon::now()->subWeeks($goalData['weeks_ago'][$i])->format('Y-m-d'),
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
