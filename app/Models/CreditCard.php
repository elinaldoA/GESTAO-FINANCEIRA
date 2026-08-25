<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class CreditCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'limit_amount', 'closing_day', 'due_day', 'color', 'is_active',
    ];

    protected $casts = [
        'limit_amount' => 'decimal:2',
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

    public function getUsedLimitAttribute(): float
    {
        return (float) $this->transactions()
            ->where('type', 'despesa')
            ->where('invoice_paid', false)
            ->sum('amount');
    }

    public function getAvailableLimitAttribute(): float
    {
        return (float) $this->limit_amount - $this->used_limit;
    }

    public function invoiceClosingDate(int $year, int $month): Carbon
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        return Carbon::create($year, $month, min($this->closing_day, $daysInMonth));
    }

    public function invoiceDueDate(int $year, int $month): Carbon
    {
        $dueMonth = $this->invoiceClosingDate($year, $month);

        if ($this->due_day < $this->closing_day) {
            $dueMonth = $dueMonth->copy()->addMonthNoOverflow();
        }

        return Carbon::create($dueMonth->year, $dueMonth->month, min($this->due_day, $dueMonth->daysInMonth));
    }

    public function invoicePeriod(int $year, int $month): array
    {
        $closing = $this->invoiceClosingDate($year, $month);
        $start = $closing->copy()->subMonthNoOverflow()->addDay();

        return [$start, $closing];
    }

    public function invoiceTransactionsQuery(int $year, int $month)
    {
        [$start, $end] = $this->invoicePeriod($year, $month);

        return $this->transactions()
            ->where('type', 'despesa')
            ->whereBetween('date', [$start->startOfDay(), $end->endOfDay()])
            ->orderBy('date');
    }
}
