<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Investment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'investment_type_id', 'name', 'broker', 'ticker', 'quantity',
        'invested_amount', 'current_amount', 'color', 'is_active',
        'quote_updated_at', 'day_change_percent', 'week52_low', 'week52_high',
        'price_earnings', 'price_to_book', 'dividend_yield',
    ];

    protected $casts = [
        'invested_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'quantity' => 'decimal:8',
        'day_change_percent' => 'decimal:2',
        'week52_low' => 'decimal:2',
        'week52_high' => 'decimal:2',
        'price_earnings' => 'decimal:2',
        'price_to_book' => 'decimal:2',
        'dividend_yield' => 'decimal:2',
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

    /** @return HasMany<Dividend, $this> */
    public function dividends(): HasMany
    {
        return $this->hasMany(Dividend::class);
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

    public function getCurrentPriceAttribute(): ?float
    {
        if (! $this->quantity || (float) $this->quantity <= 0) {
            return null;
        }

        return (float) $this->current_amount / (float) $this->quantity;
    }

    public function getAveragePriceAttribute(): ?float
    {
        if (! $this->quantity || (float) $this->quantity <= 0) {
            return null;
        }

        return (float) $this->invested_amount / (float) $this->quantity;
    }

    public function getTotalDividendsReceivedAttribute(): float
    {
        return (float) $this->dividends()->sum('amount');
    }

    /**
     * Applies a quote fetched from StockQuoteService::fetchQuote() to this investment,
     * recalculating current_amount from price x quantity and persisting the indicators
     * that came with it. Centralized here because it's called from the scheduled command,
     * the manual "refresh" action and the auto-poll action alike.
     *
     * @param  array{price: float, changePercent: ?float, week52Low: ?float, week52High: ?float, priceEarnings: ?float, priceToBook: ?float, dividendYield: ?float}  $quote
     */
    public function applyQuote(array $quote): void
    {
        $this->update([
            'current_amount' => round($quote['price'] * (float) $this->quantity, 2),
            'day_change_percent' => $quote['changePercent'],
            'week52_low' => $quote['week52Low'],
            'week52_high' => $quote['week52High'],
            'price_earnings' => $quote['priceEarnings'],
            'price_to_book' => $quote['priceToBook'],
            'dividend_yield' => $quote['dividendYield'],
            'quote_updated_at' => now(),
        ]);
    }
}
