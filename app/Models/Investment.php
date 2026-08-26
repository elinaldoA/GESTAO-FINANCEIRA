<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Investment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'investment_type_id', 'name', 'broker', 'ticker', 'quantity',
        'invested_amount', 'current_amount', 'color', 'is_active',
        'quote_updated_at', 'day_change_percent',
    ];

    protected $casts = [
        'invested_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'quantity' => 'decimal:8',
        'day_change_percent' => 'decimal:2',
        'is_active' => 'boolean',
        'quote_updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function investmentType(): BelongsTo
    {
        return $this->belongsTo(InvestmentType::class);
    }

    public function getGainAttribute(): float
    {
        return (float) $this->current_amount - (float) $this->invested_amount;
    }

    public function getGainPercentAttribute(): float
    {
        if ((float) $this->invested_amount <= 0) {
            return 0.0;
        }

        return ($this->gain / (float) $this->invested_amount) * 100;
    }
}
