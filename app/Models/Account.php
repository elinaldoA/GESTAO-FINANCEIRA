<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'type', 'initial_balance', 'color', 'is_active',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'destination_account_id');
    }

    public function getCurrentBalanceAttribute(): float
    {
        $income = $this->transactions()->where('type', 'receita')->where('is_paid', true)->sum('amount');
        $expense = $this->transactions()->where('type', 'despesa')->where('is_paid', true)->sum('amount');
        $transferOut = $this->transactions()->where('type', 'transferencia')->where('is_paid', true)->sum('amount');
        $transferIn = $this->incomingTransfers()->where('type', 'transferencia')->where('is_paid', true)->sum('amount');

        return (float) $this->initial_balance + $income - $expense - $transferOut + $transferIn;
    }
}
