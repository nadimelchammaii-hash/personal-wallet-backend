<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Transaction */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'note' => $this->note,
            'transaction_date' => $this->transaction_date->toDateString(),
            'transfer_group_id' => $this->transfer_group_id,
            'account' => [
                'id' => $this->account->id,
                'name' => $this->account->name,
                'icon' => $this->account->icon,
            ],
            'related_account' => $this->whenLoaded('relatedAccount', fn () => $this->relatedAccount ? [
                'id' => $this->relatedAccount->id,
                'name' => $this->relatedAccount->name,
                'icon' => $this->relatedAccount->icon,
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'icon' => $this->category->icon,
                'color' => $this->category->color,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
