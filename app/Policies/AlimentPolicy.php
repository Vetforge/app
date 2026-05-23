<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Aliment;
use App\Models\User;

class AlimentPolicy
{
    public function view(User $user, Aliment $aliment): bool
    {
        return $aliment->user_id === null || $user->id === $aliment->user_id || $user->is_admin;
    }

    public function update(User $user, Aliment $aliment): bool
    {
        if ($aliment->code_inra !== null && ! $user->is_admin) {
            return false;
        }

        return $user->id === $aliment->user_id || $user->is_admin;
    }

    public function delete(User $user, Aliment $aliment): bool
    {
        if ($aliment->code_inra !== null && ! $user->is_admin) {
            return false;
        }

        return $user->id === $aliment->user_id || $user->is_admin;
    }
}
