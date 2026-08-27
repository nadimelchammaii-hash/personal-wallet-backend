<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Accounts\StoreAccountRequest;
use App\Http\Requests\Api\V1\Accounts\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = $request->user()->accounts()
            ->when(! $request->boolean('include_archived'), fn ($query) => $query->where('is_archived', false))
            ->orderBy('created_at')
            ->get();

        return AccountResource::collection($accounts);
    }

    public function store(StoreAccountRequest $request): AccountResource
    {
        $account = $request->user()->accounts()->create([
            ...$request->validated(),
            'current_balance' => $request->validated('initial_balance'),
            'icon' => $request->validated('icon') ?? Account::DEFAULT_ICONS[$request->validated('type')],
        ]);

        return new AccountResource($account);
    }

    public function show(Account $account): AccountResource
    {
        Gate::authorize('view', $account);

        return new AccountResource($account);
    }

    public function update(UpdateAccountRequest $request, Account $account): AccountResource
    {
        $account->update([
            ...$request->validated(),
            'current_balance' => $request->validated('initial_balance'),
            'icon' => $request->validated('icon') ?? Account::DEFAULT_ICONS[$request->validated('type')],
        ]);

        return new AccountResource($account);
    }

    public function destroy(Account $account): Response
    {
        Gate::authorize('delete', $account);

        $account->delete();

        return response()->noContent();
    }
}
