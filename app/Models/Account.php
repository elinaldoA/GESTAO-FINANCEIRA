<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Account extends Model
{
    use HasFactory, SoftDeletes;

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
        $totals = DB::table('transactions')
            ->where('is_paid', true)
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->where('account_id', $this->id)->orWhere('destination_account_id', $this->id);
            })
            ->selectRaw("SUM(CASE WHEN account_id = ? AND type = 'receita' THEN amount ELSE 0 END) AS income", [$this->id])
            ->selectRaw("SUM(CASE WHEN account_id = ? AND type = 'despesa' THEN amount ELSE 0 END) AS expense", [$this->id])
            ->selectRaw("SUM(CASE WHEN account_id = ? AND type = 'transferencia' THEN amount ELSE 0 END) AS transfer_out", [$this->id])
            ->selectRaw("SUM(CASE WHEN destination_account_id = ? AND type = 'transferencia' THEN amount ELSE 0 END) AS transfer_in", [$this->id])
            ->first();

        return (float) $this->initial_balance
            + (float) ($totals->income ?? 0)
            - (float) ($totals->expense ?? 0)
            - (float) ($totals->transfer_out ?? 0)
            + (float) ($totals->transfer_in ?? 0);
    }
}
