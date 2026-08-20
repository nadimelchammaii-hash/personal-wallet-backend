<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transactions\ListTransactionsRequest;
use App\Http\Requests\Api\V1\Transactions\StoreTransactionRequest;
use App\Http\Requests\Api\V1\Transactions\StoreTransferRequest;
use App\Http\Requests\Api\V1\Transactions\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    public function __construct(protected TransactionService $transactions) {}

    public function index(ListTransactionsRequest $request): AnonymousResourceCollection
    {
        $transactions = $request->user()->transactions()
            ->with(['account', 'category', 'relatedAccount'])
            ->when($request->filled('account_id'), fn ($query) => $query->where('account_id', $request->integer('account_id')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('transaction_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('transaction_date', '<=', $request->date('date_to')))
            ->when($request->filled('search'), fn ($query) => $query->where('note', 'like', '%'.$request->string('search').'%'))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return TransactionResource::collection($transactions);
    }

    public function store(StoreTransactionRequest $request): TransactionResource
    {
        $transaction = $this->transactions->create($request->user(), $request->validated());

        return new TransactionResource($transaction);
    }

    public function show(Transaction $transaction): TransactionResource
    {
        Gate::authorize('view', $transaction);

        return new TransactionResource($transaction->load(['account', 'category', 'relatedAccount']));
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): TransactionResource
    {
        $transaction = $this->transactions->update($transaction, $request->validated());

        return new TransactionResource($transaction);
    }

    public function destroy(Transaction $transaction): Response
    {
        Gate::authorize('delete', $transaction);

        $this->transactions->delete($transaction);

        return response()->noContent();
    }

    public function transfer(StoreTransferRequest $request): JsonResponse
    {
        [$outgoing, $incoming] = $this->transactions->createTransfer($request->user(), $request->validated());

        // A resource collection's status is always 200 by default — Laravel's
        // auto-201 detection only applies to a single Eloquent-backed resource.
        return TransactionResource::collection([$outgoing, $incoming])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
