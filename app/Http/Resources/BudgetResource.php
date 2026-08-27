<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Budget
 *
 * @property float $spent_amount Set by the controller from a single aggregate query,
 *                                not a real column — avoids an N+1 per budget.
 */
class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $spent = (float) ($this->spent_amount ?? 0);
        $amount = (float) $this->amount;
        $percentageUsed = $amount > 0 ? round(($spent / $amount) * 100, 1) : 0;

        return [
            'id' => $this->id,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'icon' => $this->category->icon,
                'color' => $this->category->color,
            ],
            'amount' => $this->amount,
            'period_month' => $this->period_month,
            'period_year' => $this->period_year,
            'spent' => number_format($spent, 2, '.', ''),
            'remaining' => number_format($amount - $spent, 2, '.', ''),
            'percentage_used' => $percentageUsed,
            'is_over_budget' => $spent > $amount,
            'created_at' => $this->created_at,
        ];
    }
}
