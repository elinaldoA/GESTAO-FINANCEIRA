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
        'user_id', 'investment_type_id', 'name', 'broker', 'invested_amount', 'current_amount', 'color', 'is_active',
    ];

    protected $casts = [
        'invested_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'is_active' => 'boolean',
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
