<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Goals\StoreContributionRequest;
use App\Http\Requests\Api\V1\Goals\StoreGoalRequest;
use App\Http\Requests\Api\V1\Goals\UpdateGoalRequest;
use App\Http\Resources\GoalContributionResource;
use App\Http\Resources\SavingsGoalResource;
use App\Models\SavingsGoal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GoalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $goals = $request->user()->savingsGoals()->orderBy('created_at', 'desc')->get();

        return SavingsGoalResource::collection($goals);
    }

    public function store(StoreGoalRequest $request): SavingsGoalResource
    {
        $goal = $request->user()->savingsGoals()->create($request->validated());

        // refresh(), not fresh(): current_amount is a DB-defaulted column not
        // included in the insert, and refresh() re-syncs this same instance
        // (keeping wasRecentlyCreated, unlike fresh() which returns a new one).
        $goal->refresh();

        return new SavingsGoalResource($goal);
    }

    public function update(UpdateGoalRequest $request, SavingsGoal $goal): SavingsGoalResource
    {
        $goal->update($request->validated());

        return new SavingsGoalResource($goal);
    }

    public function destroy(SavingsGoal $goal): Response
    {
        Gate::authorize('delete', $goal);

        $goal->delete();

        return response()->noContent();
    }

    public function contributions(SavingsGoal $goal): AnonymousResourceCollection
    {
        Gate::authorize('view', $goal);

        return GoalContributionResource::collection(
            $goal->contributions()->orderBy('contributed_at', 'desc')->get()
        );
    }

    public function addContribution(StoreContributionRequest $request, SavingsGoal $goal): GoalContributionResource
    {
        $contribution = DB::transaction(function () use ($request, $goal) {
            $contribution = $goal->contributions()->create($request->validated());

            $goal->increment('current_amount', $request->validated('amount'));

            if ($goal->status === 'active' && $goal->fresh()->current_amount >= $goal->target_amount) {
                $goal->update(['status' => 'completed']);
            }

            return $contribution;
        });

        return new GoalContributionResource($contribution);
    }
}
