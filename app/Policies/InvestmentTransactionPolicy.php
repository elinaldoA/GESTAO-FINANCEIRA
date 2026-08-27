<?php

namespace App\Policies;

use App\Models\InvestmentTransaction;
use App\Models\User;

class InvestmentTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InvestmentTransaction $investmentTransaction): bool
    {
        return $user->id === $investmentTransaction->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, InvestmentTransaction $investmentTransaction): bool
    {
        return $user->id === $investmentTransaction->user_id;
    }

    public function delete(User $user, InvestmentTransaction $investmentTransaction): bool
    {
        return $user->id === $investmentTransaction->user_id;
    }
}
