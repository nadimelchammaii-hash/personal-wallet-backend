<?php

namespace App\Http\Requests\Api\V1\Transactions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'from_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'to_account_id' => [
                'required',
                'different:from_account_id',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'note' => ['nullable', 'string', 'max:2000'],
            'transaction_date' => ['required', 'date'],
        ];
    }
}
