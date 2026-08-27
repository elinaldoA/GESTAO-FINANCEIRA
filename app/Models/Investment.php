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
        'invested_amount', 'current_amount', 'realized_gain', 'color', 'is_active',
        'quote_updated_at', 'quote_failed_at', 'day_change_percent', 'week52_low', 'week52_high',
        'price_earnings', 'price_to_book', 'dividend_yield',
    ];

    protected $casts = [
        'invested_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'realized_gain' => 'decimal:2',
        'quantity' => 'decimal:8',
        'day_change_percent' => 'decimal:2',
        'week52_low' => 'decimal:2',
        'week52_high' => 'decimal:2',
        'price_earnings' => 'decimal:2',
        'price_to_book' => 'decimal:2',
        'dividend_yield' => 'decimal:2',
        'is_active' => 'boolean',
        'quote_updated_at' => 'datetime',
        'quote_failed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<InvestmentType, $this> */
    public function investmentType(): BelongsTo
    {
        return $this->belongsTo(InvestmentType::class);
    }

    /** @return HasMany<Dividend, $this> */
    public function dividends(): HasMany
    {
        return $this->hasMany(Dividend::class);
    }

    /** @return HasMany<InvestmentTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(InvestmentTransaction::class);
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
     * Estimated gain after income tax, using the tax rate configured on the
     * investment's type (see InvestmentType::$tax_rate). Null when there's no
     * type or no rate configured — this is a user-editable estimate, not a
     * real tax calculation, so we never guess a rate on their behalf. Losses
     * aren't discounted since capital losses aren't taxed.
     */
    public function getNetGainAttribute(): ?float
    {
        $taxRate = $this->investmentType?->tax_rate;

        if ($taxRate === null) {
            return null;
        }

        if ($this->gain <= 0) {
            return $this->gain;
        }

        return $this->gain - ($this->gain * ((float) $taxRate / 100));
    }

    public function getNetGainPercentAttribute(): ?float
    {
        if ($this->net_gain === null || (float) $this->invested_amount <= 0) {
            return null;
        }

        return ($this->net_gain / (float) $this->invested_amount) * 100;
    }

    /**
     * Coarse freshness signal for the ticker's last quote fetch, used to badge
     * the UI. Null when the investment isn't tracked via ticker at all.
     */
    public function getQuoteStatusAttribute(): ?string
    {
        if (! $this->ticker) {
            return null;
        }

        $failedAfterLastSuccess = $this->quote_failed_at !== null
            && ($this->quote_updated_at === null || $this->quote_failed_at->greaterThan($this->quote_updated_at));

        if ($failedAfterLastSuccess) {
            return 'failing';
        }

        if ($this->quote_updated_at === null) {
            return 'never';
        }

        if ($this->quote_updated_at->lt(now()->subMinutes(20))) {
            return 'stale';
        }

        return 'fresh';
    }

    public function markQuoteFailed(): void
    {
        $this->update(['quote_failed_at' => now()]);
    }

    /**
     * Replays this investment's transaction ledger (see InvestmentTransaction)
     * to recompute quantity, invested_amount and realized_gain from scratch.
     * Called after any transaction is created, edited or deleted — recomputing
     * from the full history (instead of applying incremental deltas) keeps
     * editing/deleting an old transaction trivially correct.
     */
    public function recalculateFromTransactions(): void
    {
        $balance = $this->previewBalance();

        $updates = [
            'invested_amount' => $balance['invested_amount'],
            'realized_gain' => $balance['realized_gain'],
        ];

        if ($this->ticker) {
            $updates['quantity'] = $balance['quantity'];
        }

        $this->update($updates);
    }

    /**
     * Computes what quantity/invested_amount/realized_gain would be from the
     * transaction ledger without persisting anything, optionally leaving one
     * transaction out. Used to validate a venda/resgate against the balance
     * available *before* that transaction (excluding itself when editing),
     * so the form can reject an overselling/overdrawing lançamento up front.
     *
     * @return array{quantity: float, invested_amount: float, realized_gain: float}
     */
    public function previewBalance(?int $excludingTransactionId = null): array
    {
        $transactions = $this->transactions()
            ->when($excludingTransactionId, fn ($query) => $query->whereKeyNot($excludingTransactionId))
            ->orderBy('date')->orderBy('id')->get();

        // Uses the average cost method: a sale/withdrawal removes cost at the
        // running average price and books the difference against the sale
        // proceeds as realized gain/loss.
        $quantity = 0.0;
        $invested = 0.0;
        $realized = 0.0;
        $isTicker = (bool) $this->ticker;

        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->amount;

            if ($isTicker) {
                $txQuantity = (float) $transaction->quantity;

                if ($transaction->type === 'compra') {
                    $quantity += $txQuantity;
                    $invested += $amount;
                } elseif ($transaction->type === 'venda') {
                    $avgCost = $quantity > 0 ? $invested / $quantity : 0.0;
                    $costRemoved = min($invested, $avgCost * $txQuantity);
                    $realized += $amount - $costRemoved;
                    $quantity = max(0.0, $quantity - $txQuantity);
                    $invested = max(0.0, $invested - $costRemoved);
                }
            } else {
                if ($transaction->type === 'aporte') {
                    $invested += $amount;
                } elseif ($transaction->type === 'resgate') {
                    $invested = max(0.0, $invested - $amount);
                }
            }
        }

        return [
            'quantity' => round($quantity, 8),
            'invested_amount' => round($invested, 2),
            'realized_gain' => round($realized, 2),
        ];
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
