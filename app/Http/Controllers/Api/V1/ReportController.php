<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reports\MonthYearRequest;
use App\Http\Requests\Api\V1\Reports\SpendingTrendsRequest;
use App\Models\Budget;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function spendingTrends(SpendingTrendsRequest $request): JsonResponse
    {
        $months = $request->integer('months', 6);
        $user = $request->user();
        $now = now();

        $start = $now->copy()->subMonths($months - 1)->startOfMonth();

        $transactions = $user->transactions()
            ->whereIn('type', ['income', 'expense'])
            ->where('transaction_date', '>=', $start->toDateString())
            ->get(['type', 'amount', 'transaction_date']);

        $trends = collect(range($months - 1, 0))->map(function (int $offset) use ($now, $transactions) {
            $period = $now->copy()->subMonths($offset);

            $inPeriod = $transactions->filter(
                fn ($transaction) => $transaction->transaction_date->year === $period->year
                    && $transaction->transaction_date->month === $period->month
            );

            return [
                'period' => $period->format('Y-m'),
                'income' => number_format((float) $inPeriod->where('type', 'income')->sum('amount'), 2, '.', ''),
                'expenses' => number_format((float) $inPeriod->where('type', 'expense')->sum('amount'), 2, '.', ''),
            ];
        })->values();

        return response()->json(['data' => $trends]);
    }

    public function categoryBreakdown(MonthYearRequest $request): JsonResponse
    {
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);
        $user = $request->user();

        $breakdown = $user->transactions()
            ->with('category')
            ->where('type', 'expense')
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
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

        return response()->json(['data' => $breakdown]);
    }

    public function budgetPerformance(MonthYearRequest $request): JsonResponse
    {
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);
        $user = $request->user();

        $budgets = $user->budgets()
            ->with('category')
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->get();

        $spentByCategory = $user->transactions()
            ->where('type', 'expense')
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $performance = $budgets->map(fn (Budget $budget) => [
            'category' => [
                'id' => $budget->category->id,
                'name' => $budget->category->name,
                'icon' => $budget->category->icon,
                'color' => $budget->category->color,
            ],
            'budgeted' => number_format((float) $budget->amount, 2, '.', ''),
            'spent' => number_format((float) ($spentByCategory[$budget->category_id] ?? 0), 2, '.', ''),
        ])->values();

        return response()->json(['data' => $performance]);
    }
}
