<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SavingsGoalResource;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = now();

        $balance = $user->accounts()->where('is_archived', false)->sum('current_balance');

        $income = $user->transactions()
            ->where('type', 'income')
            ->whereMonth('transaction_date', $now->month)
            ->whereYear('transaction_date', $now->year)
            ->sum('amount');

        $expenses = $user->transactions()
            ->where('type', 'expense')
            ->whereMonth('transaction_date', $now->month)
            ->whereYear('transaction_date', $now->year)
            ->sum('amount');

        $budgets = $user->budgets()
            ->where('period_month', $now->month)
            ->where('period_year', $now->year)
            ->get();

        $remainingBudget = null;
        if ($budgets->isNotEmpty()) {
            $spentByCategory = $user->transactions()
                ->where('type', 'expense')
                ->whereMonth('transaction_date', $now->month)
                ->whereYear('transaction_date', $now->year)
                ->selectRaw('category_id, SUM(amount) as total')
                ->groupBy('category_id')
                ->pluck('total', 'category_id');

            $totalBudgeted = (float) $budgets->sum('amount');
            $totalSpent = (float) $budgets->sum(fn ($budget) => (float) ($spentByCategory[$budget->category_id] ?? 0));
            $remainingBudget = number_format($totalBudgeted - $totalSpent, 2, '.', '');
        }

        $spendingByCategory = $user->transactions()
            ->with('category')
            ->where('type', 'expense')
            ->whereMonth('transaction_date', $now->month)
            ->whereYear('transaction_date', $now->year)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category' => [
                    'id' => $row->category->id,
                    'name' => $row->category->name,
                    'icon' => $row->category->icon,
                    'color' => $row->category->color,
                ],
                'amount' => number_format((float) $row->total, 2, '.', ''),
            ])
            ->values();

        $recentTransactions = $user->transactions()
            ->with(['account', 'category', 'relatedAccount'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $goals = $user->savingsGoals()
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->limit(4)
            ->get();

        return response()->json([
            'data' => [
                'balance' => number_format((float) $balance, 2, '.', ''),
                'income' => number_format((float) $income, 2, '.', ''),
                'expenses' => number_format((float) $expenses, 2, '.', ''),
                'savings' => number_format((float) $income - (float) $expenses, 2, '.', ''),
                'remaining_budget' => $remainingBudget,
                'recent_transactions' => TransactionResource::collection($recentTransactions),
                'spending_by_category' => $spendingByCategory,
                'goals' => SavingsGoalResource::collection($goals),
            ],
        ]);
    }
}
