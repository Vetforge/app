<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class CreateUserCommand extends Command
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    /**
     * Nom et signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'users:create';

    /**
     * Description de la commande console.
     *
     * @var string
     */
    protected $description = 'Create a user interactively with Laravel Prompts';

    public function handle(): int
    {
        intro('Creation d\'un utilisateur');

        note('Les regles de validation sont les memes que pour l\'inscription Laravel.', 'info');

        $defaults = [
            'name' => '',
            'email' => '',
            'is_admin' => false,
        ];

        while (true) {
            $name = text(
                label: 'Nom complet',
                placeholder: 'Jean Dupont',
                default: $defaults['name'],
                required: 'Le nom est obligatoire.',
            );

            $email = text(
                label: 'Adresse email',
                placeholder: 'jean@example.com',
                default: $defaults['email'],
                required: 'L\'email est obligatoire.',
            );

            $password = password(
                label: 'Mot de passe',
                placeholder: 'Minimum 8 caracteres',
                required: 'Le mot de passe est obligatoire.',
                hint: 'Le mot de passe doit respecter la politique Laravel par defaut.',
            );

            $passwordConfirmation = password(
                label: 'Confirmation du mot de passe',
                required: 'La confirmation du mot de passe est obligatoire.',
            );

            $isAdmin = confirm(
                label: 'Donner les droits administrateur ?',
                default: $defaults['is_admin'],
                yes: 'Admin',
                no: 'Standard',
            );

            $input = [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
                'is_admin' => $isAdmin,
            ];

            $validator = Validator::make($input, [
                ...$this->profileRules(),
                'password' => $this->passwordRules(),
                'is_admin' => ['boolean'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    error($message);
                }

                warning('Merci de corriger les champs en erreur.');

                $defaults = [
                    'name' => $name,
                    'email' => $email,
                    'is_admin' => $isAdmin,
                ];

                continue;
            }

            table(
                headers: ['Nom', 'Email', 'Role'],
                rows: [[
                    $name,
                    $email,
                    $isAdmin ? 'Administrateur' : 'Utilisateur',
                ]],
            );

            if (! confirm('Creer cet utilisateur ?', default: true, yes: 'Creer', no: 'Annuler')) {
                outro('Creation annulee.');

                return self::SUCCESS;
            }

            /** @var User $user */
            $user = spin(
                callback: fn (): User => User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'is_admin' => $isAdmin,
                ]),
                message: 'Creation du compte...',
            );

            info("Utilisateur #{$user->id} cree.");
            outro("Le compte {$user->email} est pret.");

            return self::SUCCESS;
        }
    }
}
