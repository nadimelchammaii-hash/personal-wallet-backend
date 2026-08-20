<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Budgets\ListBudgetsRequest;
use App\Http\Requests\Api\V1\Budgets\StoreBudgetRequest;
use App\Http\Requests\Api\V1\Budgets\UpdateBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BudgetController extends Controller
{
    public function index(ListBudgetsRequest $request): AnonymousResourceCollection
    {
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        $budgets = $request->user()->budgets()
            ->with('category')
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->orderBy('id')
            ->get();

        // One aggregate query for every category's spend this period, instead
        // of one query per budget.
        $spentByCategory = $request->user()->transactions()
            ->where('type', 'expense')
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $budgets->each(function (Budget $budget) use ($spentByCategory): void {
            $budget->spent_amount = (float) ($spentByCategory[$budget->category_id] ?? 0);
        });

        return BudgetResource::collection($budgets);
    }

    public function store(StoreBudgetRequest $request): BudgetResource
    {
        $budget = $request->user()->budgets()->create($request->validated())->load('category');
        $budget->spent_amount = 0;

        return new BudgetResource($budget);
    }

    public function update(UpdateBudgetRequest $request, Budget $budget): BudgetResource
    {
        $budget->update($request->validated());
        $budget->load('category');

        $budget->spent_amount = (float) $budget->user->transactions()
            ->where('type', 'expense')
            ->where('category_id', $budget->category_id)
            ->whereMonth('transaction_date', $budget->period_month)
            ->whereYear('transaction_date', $budget->period_year)
            ->sum('amount');

        return new BudgetResource($budget);
    }

    public function destroy(Budget $budget): Response
    {
        Gate::authorize('delete', $budget);

        $budget->delete();

        return response()->noContent();
    }
}
