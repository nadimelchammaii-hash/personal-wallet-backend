<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GoalContribution */
class GoalContributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'contributed_at' => $this->contributed_at->toDateString(),
            'note' => $this->note,
            'created_at' => $this->created_at,
        ];
    }
}
