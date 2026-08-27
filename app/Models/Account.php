<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'currency', 'initial_balance', 'current_balance', 'color', 'icon', 'is_archived'])]
class Account extends Model
{
    use HasFactory;

    public const array TYPES = ['cash', 'bank', 'credit_card', 'savings', 'other'];

    public const array DEFAULT_ICONS = [
        'cash' => 'mdi-cash',
        'bank' => 'mdi-bank',
        'credit_card' => 'mdi-credit-card',
        'savings' => 'mdi-piggy-bank',
        'other' => 'mdi-wallet',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_archived' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
