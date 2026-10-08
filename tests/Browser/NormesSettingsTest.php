<?php

use App\Models\User;
use App\Support\RationNormes;

it('lets users edit and reload all supported custom ration bounds', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $page = visit(route('normes.edit', absolute: false));

    foreach (RationNormes::editable() as $definition) {
        if ($definition['supports_min']) {
            $page->assertPresent('#'.$definition['key'].'-min');
        }
        if ($definition['supports_max']) {
            $page->assertPresent('#'.$definition['key'].'-max');
        }
    }

    $page->fill('#bpr-min', '1,25')->fill('#bpr-max', '4,75')->fill('#ph_ruminal-min', '6,4')
        ->click('Enregistrer')->assertSee('Enregistré.')->assertNoJavaScriptErrors();
    expect($user->fresh()->normes_personnalisees)->toMatchArray([
        'bpr' => ['min' => 1.25, 'max' => 4.75], 'ph_ruminal' => ['min' => 6.4, 'max' => null],
    ]);

    visit(route('normes.edit', absolute: false))->assertValue('#bpr-min', '1.25')->assertValue('#bpr-max', '4.75')
        ->assertValue('#ph_ruminal-min', '6.4')->assertNoJavaScriptErrors();
});

it('shows validation errors and preserves the draft when saving invalid bounds', function () {
    $user = User::factory()->create(['normes_personnalisees' => ['bpr' => ['min' => 1, 'max' => 4]]]);
    $this->actingAs($user);

    visit(route('normes.edit', absolute: false))
        ->fill('#bpr-min', '5')->fill('#bpr-max', '4')->click('Enregistrer')
        ->assertSee('La borne haute doit être supérieure ou égale à la borne basse.')
        ->assertValue('#bpr-min', '5')->assertDontSee('Enregistré.')
        ->fill('#bpr-min', 'abc')->click('Enregistrer')
        ->assertSee('La borne basse doit être un nombre.')->assertValue('#bpr-min', 'abc')
        ->assertNoJavaScriptErrors();
    expect($user->fresh()->normes_personnalisees['bpr'])->toBe(['min' => 1, 'max' => 4]);
});

it('can clear a threshold and restore defaults from the settings page', function () {
    $user = User::factory()->create(['normes_personnalisees' => ['bpr' => ['min' => 1, 'max' => 4]]]);
    $this->actingAs($user);
    visit(route('normes.edit', absolute: false))
        ->fill('#bil_ufl-min', '')->click('Enregistrer')->assertSee('Enregistré.');
    visit(route('normes.edit', absolute: false))->assertValue('#bil_ufl-min', '')
        ->click('Réinitialiser')->assertValue('#bpr-min', '')->assertValue('#bpr-max', '')->assertValue('#bil_ufl-min', '0')
        ->click('Enregistrer')->assertSee('Enregistré.')->assertNoJavaScriptErrors();
    expect($user->fresh()->normes_personnalisees)->toBeNull();
});
