<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dividend extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = [
        'dividendo' => 'Dividendo',
        'jcp' => 'JCP',
        'rendimento' => 'Rendimento',
        'outro' => 'Outro',
    ];

    protected $fillable = [
        'user_id', 'investment_id', 'date', 'type', 'amount', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
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
