<?php

namespace App\Policies;

use App\Models\InvestmentType;
use App\Models\User;

class InvestmentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InvestmentType $investmentType): bool
    {
        return $user->id === $investmentType->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, InvestmentType $investmentType): bool
    {
        return $user->id === $investmentType->user_id;
    }

    public function delete(User $user, InvestmentType $investmentType): bool
    {
        return $user->id === $investmentType->user_id;
    }
}
