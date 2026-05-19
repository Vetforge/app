<?php

namespace App\Policies;

use App\Models\PlanRationnement;
use App\Models\User;

class PlanRationnementPolicy
{
    public function view(User $user, PlanRationnement $plan): bool
    {
        return $user->id === $plan->user_id || $user->is_admin;
    }

    public function update(User $user, PlanRationnement $plan): bool
    {
        return $user->id === $plan->user_id || $user->is_admin;
    }

    public function delete(User $user, PlanRationnement $plan): bool
    {
        return $user->id === $plan->user_id || $user->is_admin;
    }
}
