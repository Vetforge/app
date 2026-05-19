<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an admin user from the interactive command', function () {
    $this->artisan('users:create')
        ->expectsPromptsIntro('Creation d\'un utilisateur')
        ->expectsPromptsInfo('Les regles de validation sont les memes que pour l\'inscription Laravel.')
        ->expectsQuestion('Nom complet', 'Jean Dupont')
        ->expectsQuestion('Adresse email', 'jean@example.com')
        ->expectsQuestion('Mot de passe', 'secret-password')
        ->expectsQuestion('Confirmation du mot de passe', 'secret-password')
        ->expectsConfirmation('Donner les droits administrateur ?', 'yes')
        ->expectsPromptsTable(
            ['Nom', 'Email', 'Role'],
            [['Jean Dupont', 'jean@example.com', 'Administrateur']],
        )
        ->expectsConfirmation('Creer cet utilisateur ?', 'yes')
        ->expectsPromptsInfo('Utilisateur #1 cree.')
        ->expectsPromptsOutro('Le compte jean@example.com est pret.')
        ->assertSuccessful();

    $user = User::query()->first();

    expect($user)
        ->not->toBeNull()
        ->and($user->name)->toBe('Jean Dupont')
        ->and($user->email)->toBe('jean@example.com')
        ->and($user->is_admin)->toBeTrue()
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});
