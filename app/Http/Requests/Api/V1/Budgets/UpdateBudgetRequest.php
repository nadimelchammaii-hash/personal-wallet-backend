<?php

namespace App\Http\Requests\Api\V1\Budgets;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('budget'));
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('user_id', $userId)->orWhereNull('user_id')
                ),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'between:2000,2100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('category_id')) {
                return;
            }

            $category = Category::find($this->input('category_id'));

            if ($category && $category->type !== 'expense') {
                $validator->errors()->add('category_id', 'Budgets can only be set for expense categories.');

                return;
            }

            if (! $this->filled('period_month') || ! $this->filled('period_year')) {
                return;
            }

            $exists = $this->user()->budgets()
                ->where('category_id', $this->input('category_id'))
                ->where('period_month', $this->input('period_month'))
                ->where('period_year', $this->input('period_year'))
                ->whereKeyNot($this->route('budget')->id)
                ->exists();

            if ($exists) {
                $validator->errors()->add('category_id', 'A budget already exists for this category and period.');
            }
        });
    }
}
