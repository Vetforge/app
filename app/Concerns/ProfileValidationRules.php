<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Obtenir les règles de validation utilisées pour valider les profils utilisateur.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'clinic_profile' => ['nullable', 'array'],
            'clinic_profile.name' => ['nullable', 'string', 'max:255'],
            'clinic_profile.address' => ['nullable', 'string', 'max:255'],
            'clinic_profile.postal_code' => ['nullable', 'string', 'max:20'],
            'clinic_profile.city' => ['nullable', 'string', 'max:255'],
            'clinic_profile.phone' => ['nullable', 'string', 'max:50'],
            'clinic_profile.email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Obtenir les règles de validation utilisées pour valider les noms d'utilisateur.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Obtenir les règles de validation utilisées pour valider les e-mails utilisateur.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
