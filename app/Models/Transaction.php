<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    public const PAYMENT_METHODS = [
        'pix' => 'Pix',
        'debito' => 'Débito',
        'credito' => 'Crédito',
        'dinheiro' => 'Dinheiro',
        'boleto' => 'Boleto',
        'outro' => 'Outro',
    ];

    protected $fillable = [
        'user_id', 'account_id', 'credit_card_id', 'destination_account_id', 'category_id',
        'type', 'payment_method', 'description', 'amount', 'date', 'is_paid', 'is_recurring',
        'recurrence_interval', 'parent_transaction_id', 'installment_number', 'installment_total', 'notes',
        'attachment_path', 'attachment_name', 'invoice_paid', 'reconciled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'is_paid' => 'boolean',
        'is_recurring' => 'boolean',
        'invoice_paid' => 'boolean',
        'reconciled_at' => 'datetime',
    ];

    public function getIsReconciledAttribute(): bool
    {
        return $this->reconciled_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'parent_transaction_id');
    }

    public function getPaymentMethodLabelAttribute(): ?string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? null;
    }

    public function getIsInstallmentAttribute(): bool
    {
        return (int) $this->installment_total > 1;
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBetweenDates($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }
}
