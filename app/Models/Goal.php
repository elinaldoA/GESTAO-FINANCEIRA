<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'target_amount', 'current_amount', 'target_date', 'color', 'is_active',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'target_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProgressPercentAttribute(): float
    {
        if ((float) $this->target_amount <= 0) {
            return 0.0;
        }

        return min(100, ((float) $this->current_amount / (float) $this->target_amount) * 100);
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->target_amount - (float) $this->current_amount);
    }

    public function getIsAchievedAttribute(): bool
    {
        return (float) $this->current_amount >= (float) $this->target_amount;
    }
}
