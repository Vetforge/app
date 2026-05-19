<?php

use App\Models\User;

it('can visit the login page', function () {
    visit('/login')
        ->assertSee('Se connecter');
});

it('can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@vethorizons.fr',
        'password' => bcrypt('password'),
    ]);

    visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Se connecter')
        ->assertPathIsNot('/login');
});
