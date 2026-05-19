<?php

namespace App\Policies;

use App\Models\Melange;
use App\Models\User;

class MelangePolicy
{
    public function view(User $user, Melange $melange): bool
    {
        return $user->id === $melange->ration->planRationnement->user_id || $user->is_admin;
    }

    public function update(User $user, Melange $melange): bool
    {
        return $user->id === $melange->ration->planRationnement->user_id || $user->is_admin;
    }

    public function delete(User $user, Melange $melange): bool
    {
        return $user->id === $melange->ration->planRationnement->user_id || $user->is_admin;
    }
}
