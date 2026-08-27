<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'category_id', 'type', 'amount', 'note', 'transaction_date', 'transfer_group_id', 'related_account_id'])]
class Transaction extends Model
{
    use HasFactory;

    public const array TYPES = ['income', 'expense', 'transfer_in', 'transfer_out'];

    public const array TRANSFER_TYPES = ['transfer_in', 'transfer_out'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function isTransfer(): bool
    {
        return in_array($this->type, self::TRANSFER_TYPES, true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function relatedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'related_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
