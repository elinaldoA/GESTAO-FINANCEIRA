<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'category_id', 'keyword',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public static function matchCategoryFor(int $userId, string $description): ?int
    {
        $description = mb_strtolower($description);

        $rule = static::where('user_id', $userId)
            ->orderByRaw('LENGTH(keyword) DESC')
            ->get()
            ->first(fn (CategoryRule $rule) => str_contains($description, mb_strtolower($rule->keyword)));

        return $rule?->category_id;
    }
}
