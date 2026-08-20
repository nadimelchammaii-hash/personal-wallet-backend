<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SavingsGoal */
class SavingsGoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $target = (float) $this->target_amount;
        $current = (float) $this->current_amount;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'target_amount' => $this->target_amount,
            'current_amount' => $this->current_amount,
            'remaining_amount' => number_format(max($target - $current, 0), 2, '.', ''),
            'progress_percentage' => $target > 0 ? round(($current / $target) * 100, 1) : 0,
            'target_date' => $this->target_date?->toDateString(),
            'icon' => $this->icon,
            'color' => $this->color,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
