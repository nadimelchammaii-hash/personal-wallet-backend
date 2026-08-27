<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owns the transaction ledger and keeps each account's current_balance in
 * sync with it. Every write here runs inside a DB transaction so a
 * half-applied balance change can never happen.
 */
class TransactionService
{
    public function create(User $user, array $data): Transaction
    {
        return DB::transaction(function () use ($user, $data) {
            $account = $user->accounts()->findOrFail($data['account_id']);

            $transaction = $user->transactions()->create([
                'account_id' => $account->id,
                'category_id' => $data['category_id'],
                'type' => $data['type'],
                'amount' => $data['amount'],
                'note' => $data['note'] ?? null,
                'transaction_date' => $data['transaction_date'],
            ]);

            $this->applyEffect($account, $transaction->type, (float) $transaction->amount);

            // load(), not fresh(): fresh() re-fetches a new instance and loses
            // wasRecentlyCreated, which JsonResource needs to respond 201 not 200.
            return $transaction->load(['account', 'category']);
        });
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            // Reverse the transaction's current effect before applying the new one,
            // since the account, amount, or type may all have changed.
            $this->applyEffect($transaction->account, $transaction->type, (float) $transaction->amount, reverse: true);

            $account = $transaction->account_id === (int) $data['account_id']
                ? $transaction->account
                : $transaction->user->accounts()->findOrFail($data['account_id']);

            $transaction->update([
                'account_id' => $account->id,
                'category_id' => $data['category_id'],
                'type' => $data['type'],
                'amount' => $data['amount'],
                'note' => $data['note'] ?? null,
                'transaction_date' => $data['transaction_date'],
            ]);

            $this->applyEffect($account, $transaction->type, (float) $transaction->amount);

            return $transaction->fresh(['account', 'category']);
        });
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            if ($transaction->isTransfer()) {
                $this->deleteTransferGroup($transaction);

                return;
            }

            $this->applyEffect($transaction->account, $transaction->type, (float) $transaction->amount, reverse: true);
            $transaction->delete();
        });
    }

    /** @return array{0: Transaction, 1: Transaction} [outgoing leg, incoming leg] */
    public function createTransfer(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data) {
            $fromAccount = $user->accounts()->findOrFail($data['from_account_id']);
            $toAccount = $user->accounts()->findOrFail($data['to_account_id']);
            $groupId = (string) Str::uuid();

            $outgoing = $user->transactions()->create([
                'account_id' => $fromAccount->id,
                'related_account_id' => $toAccount->id,
                'type' => 'transfer_out',
                'amount' => $data['amount'],
                'note' => $data['note'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'transfer_group_id' => $groupId,
            ]);

            $incoming = $user->transactions()->create([
                'account_id' => $toAccount->id,
                'related_account_id' => $fromAccount->id,
                'type' => 'transfer_in',
                'amount' => $data['amount'],
                'note' => $data['note'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'transfer_group_id' => $groupId,
            ]);

            $this->applyEffect($fromAccount, 'transfer_out', (float) $data['amount']);
            $this->applyEffect($toAccount, 'transfer_in', (float) $data['amount']);

            return [
                $outgoing->load(['account', 'relatedAccount']),
                $incoming->load(['account', 'relatedAccount']),
            ];
        });
    }

    protected function deleteTransferGroup(Transaction $transaction): void
    {
        $legs = Transaction::where('transfer_group_id', $transaction->transfer_group_id)->get();

        foreach ($legs as $leg) {
            $this->applyEffect($leg->account, $leg->type, (float) $leg->amount, reverse: true);
        }

        Transaction::whereIn('id', $legs->pluck('id'))->delete();
    }

    /**
     * income / transfer_in increase the account's balance; expense / transfer_out
     * decrease it. Reversing (for edit/delete) just flips the direction.
     */
    protected function applyEffect(Account $account, string $type, float $amount, bool $reverse = false): void
    {
        $increases = in_array($type, ['income', 'transfer_in'], true);

        if ($reverse) {
            $increases = ! $increases;
        }

        if ($increases) {
            $account->increment('current_balance', $amount);
        } else {
            $account->decrement('current_balance', $amount);
        }
    }
}
