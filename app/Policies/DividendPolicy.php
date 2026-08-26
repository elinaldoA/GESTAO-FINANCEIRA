<?php

namespace App\Policies;

use App\Models\Dividend;
use App\Models\User;

class DividendPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Dividend $dividend): bool
    {
        return $user->id === $dividend->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Dividend $dividend): bool
    {
        return $user->id === $dividend->user_id;
    }

    public function delete(User $user, Dividend $dividend): bool
    {
        return $user->id === $dividend->user_id;
    }
}
