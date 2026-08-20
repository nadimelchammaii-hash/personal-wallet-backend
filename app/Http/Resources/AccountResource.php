<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Account */
class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'currency' => $this->currency,
            'initial_balance' => $this->initial_balance,
            'current_balance' => $this->current_balance,
            'color' => $this->color,
            'icon' => $this->icon,
            'is_archived' => $this->is_archived,
            'created_at' => $this->created_at,
        ];
    }
}
