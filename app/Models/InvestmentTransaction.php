<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvestmentTransaction extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = [
        'compra' => 'Compra',
        'venda' => 'Venda',
        'aporte' => 'Aporte',
        'resgate' => 'Resgate',
    ];

    /** Types that apply to investments tracked by ticker (quantity x price). */
    public const TICKER_TYPES = ['compra', 'venda'];

    /** Types that apply to investments without a ticker (plain cash amount). */
    public const CASH_TYPES = ['aporte', 'resgate'];

    protected $fillable = [
        'user_id', 'investment_id', 'date', 'type', 'quantity', 'unit_price', 'fees', 'amount', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'decimal:8',
        'unit_price' => 'decimal:4',
        'fees' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type];
    }
}
