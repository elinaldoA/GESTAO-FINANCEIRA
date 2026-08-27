<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'category_id', 'month', 'year', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getSpentAttribute(): float
    {
        return (float) Transaction::where('category_id', $this->category_id)
            ->where('user_id', $this->user_id)
            ->where('type', 'despesa')
            ->whereYear('date', $this->year)
            ->whereMonth('date', $this->month)
            ->sum('amount');
    }

    /**
     * Total spent per category for a given month/year, in a single query.
     * Used to avoid one `getSpentAttribute()` query per budget when rendering a list.
     *
     * @return array<int, float> keyed by category_id
     */
    public static function spentMapFor(int $userId, int $month, int $year): array
    {
        return Transaction::where('user_id', $userId)
            ->where('type', 'despesa')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(amount) as total')
            ->pluck('total', 'category_id')
            ->map(fn ($total) => (float) $total)
            ->all();
    }
}
