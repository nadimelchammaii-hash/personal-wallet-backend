<?php

namespace App\Http\Requests\Api\V1\Goals;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'target_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'target_date' => ['nullable', 'date'],
            'icon' => ['nullable', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'max:32'],
        ];
    }
}
