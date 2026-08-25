<?php

namespace App\Policies;

use App\Models\CategoryRule;
use App\Models\User;

class CategoryRulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CategoryRule $categoryRule): bool
    {
        return $user->id === $categoryRule->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CategoryRule $categoryRule): bool
    {
        return $user->id === $categoryRule->user_id;
    }

    public function delete(User $user, CategoryRule $categoryRule): bool
    {
        return $user->id === $categoryRule->user_id;
    }
}
