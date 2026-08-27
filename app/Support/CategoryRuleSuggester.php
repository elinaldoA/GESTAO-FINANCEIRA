<?php

namespace App\Support;

use App\Models\CategoryRule;
use App\Models\Transaction;

class CategoryRuleSuggester
{
    private const MIN_WORD_LENGTH = 4;

    private const MIN_OCCURRENCES = 3;

    /**
     * Suggests creating a CategoryRule when the user has manually assigned the same
     * category to several transactions that share a common word in the description,
     * and no existing rule already covers that word.
     *
     * @return array{keyword: string, category_id: int, category_name: string, count: int}|null
     */
    public static function suggestFor(int $userId, Transaction $transaction): ?array
    {
        if (! $transaction->category_id) {
            return null;
        }

        $existingKeywords = CategoryRule::where('user_id', $userId)
            ->pluck('keyword')
            ->map(fn ($keyword) => mb_strtolower($keyword))
            ->all();

        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($transaction->description)))
            ->filter(fn ($word) => mb_strlen($word) >= self::MIN_WORD_LENGTH)
            ->unique()
            ->sortByDesc(fn ($word) => mb_strlen($word));

        foreach ($words as $word) {
            if (in_array($word, $existingKeywords, true)) {
                continue;
            }

            $otherMatches = Transaction::where('user_id', $userId)
                ->where('category_id', $transaction->category_id)
                ->where('id', '!=', $transaction->id)
                ->where('description', 'like', '%'.$word.'%')
                ->count();

            $total = $otherMatches + 1;

            if ($total >= self::MIN_OCCURRENCES) {
                return [
                    'keyword' => $word,
                    'category_id' => $transaction->category_id,
                    'category_name' => $transaction->category->name ?? '',
                    'count' => $total,
                ];
            }
        }

        return null;
    }
}
