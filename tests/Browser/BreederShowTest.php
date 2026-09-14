<?php

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\User;

/**
 * Le nom d'un éleveur dans la liste doit ouvrir la page regroupant
 * toutes ses analyses, avec recherche et scroll infini.
 */
test('the breeder name links to the breeder analyses page', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'hemogramme',
        'animal_nom' => 'Titine',
    ]);

    $this->actingAs($user);

    visit(route('breeders.index', absolute: false))
        ->assertNoJavaScriptErrors()
        ->assertSee('GAEC du Val')
        ->click('GAEC du Val')
        ->assertPathIs(route('breeders.show', $breeder, absolute: false))
        ->assertNoJavaScriptErrors()
        ->assertSee('Hemogramme — Titine');
});

test('the breeder analyses page filters analyses with the search bar', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'hemogramme',
        'animal_nom' => 'Titine',
    ]);
    Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'coproscopie-parasitaire',
        'animal_nom' => 'Vache 123',
    ]);

    $this->actingAs($user);

    visit(route('breeders.show', $breeder, absolute: false))
        ->assertNoJavaScriptErrors()
        ->assertSee('Hemogramme — Titine')
        ->assertSee('Coproscopie — Vache 123')
        ->fill('search', 'titine')
        ->wait(1)
        ->assertSee('Hemogramme — Titine')
        ->assertDontSee('Coproscopie — Vache 123');
});
