<?php

use App\Models\User;
use App\Support\RationNormes;

it('lets users edit and reload all supported custom ration bounds', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $page = visit(route('module-settings.edit', ['module' => 'gaz-du-sang'], absolute: false));
    $page->click('nav[aria-label="Modules"] a[href="/settings/modules/ration"]')->assertPathIs('/settings/modules/ration')->assertSee('Réglages modules');

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
    expect($user->fresh()->normes_personnalisees['categories']['vache_laitiere'])->toMatchArray([
        'bpr' => ['min' => 1.25, 'max' => 4.75], 'ph_ruminal' => ['min' => 6.4, 'max' => null],
    ]);

    visit(route('ration-settings.edit', absolute: false))->assertValue('#bpr-min', '1.25')->assertValue('#bpr-max', '4.75')
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
    $user = User::factory()->create(['normes_personnalisees' => ['categories' => ['vache_laitiere' => ['bpr' => ['min' => 1, 'max' => 4]]]]]);
    $this->actingAs($user);
    visit(route('normes.edit', absolute: false))
        ->fill('#bil_ufl-min', '')->click('Enregistrer')->assertSee('Enregistré.');
    visit(route('normes.edit', absolute: false))->assertValue('#bil_ufl-min', '')
        ->click('Réinitialiser')->assertValue('#bpr-min', '')->assertValue('#bpr-max', '')->assertValue('#bil_ufl-min', '0')
        ->click('Enregistrer')->assertSee('Enregistré.')->assertNoJavaScriptErrors();
    expect($user->fresh()->normes_personnalisees)->toBeNull();
});

it('keeps ration settings searchable and usable on desktop and mobile', function (int $width, int $height) {
    $user = User::factory()->create();
    $this->actingAs($user);
    $page = visit(route('ration-settings.edit', absolute: false))->resize($width, $height)
        ->assertSee('Ration')->assertPresent('#ph_ruminal-max')
        ->assertPresent('#eff_pdi-max')->assertPresent('#cb_par_kg_ms-max')->assertPresent('#bil_ufl-max');
    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    if (getenv('RATION_NORMES_CAPTURE')) {
        $page->screenshot(false, 'ration-normes-'.$width.'.png');
    }

    $page->fill('#ph_ruminal-max', '6,85')
        ->fill('#ration-norm-search', 'calcium')->assertPresent('#couverture_caabs-min')
        ->assertNotPresent('#ph_ruminal-max')
        ->fill('#couverture_caabs-min', '85')->fill('#couverture_caabs-max', '125')
        ->click('Enregistrer')->assertSee('Enregistré.');
    expect($user->fresh()->normes_personnalisees['categories']['vache_laitiere'])->toMatchArray([
        'ph_ruminal' => ['min' => null, 'max' => 6.85],
        'couverture_caabs' => ['min' => 85, 'max' => 125],
    ]);
    $page->fill('#ration-norm-search', 'aucune norme')->assertSee('Aucune norme ne correspond à votre recherche.')
        ->fill('#ration-norm-search', '')->assertValue('#ph_ruminal-max', '6.85')->assertNoJavaScriptErrors();
})->with(['desktop' => [1440, 1100], 'mobile' => [390, 844]]);

it('keeps drafts and saved bounds separate when switching animal categories', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $page = visit(route('ration-settings.edit', absolute: false));
    $page->assertPresent('#ration-norm-category optgroup[label="Bovins"]')
        ->assertPresent('#ration-norm-category optgroup[label="Ovins"]')
        ->assertPresent('#ration-norm-category optgroup[label="Caprins"]')
        ->fill('#bpr-min', '1,25')
        ->select('#ration-norm-category', 'brebis_laitiere')->assertValue('#bpr-min', '')
        ->fill('#bpr-min', '2,5')->fill('#bpr-max', '5')
        ->select('#ration-norm-category', 'vache_laitiere')->assertValue('#bpr-min', '1,25')
        ->click('Enregistrer')->assertSee('Enregistré.')
        ->select('#ration-norm-category', 'brebis_laitiere')->assertDontSee('Enregistré.')
        ->assertValue('#bpr-min', '2,5')->click('Enregistrer')->assertSee('Enregistré.')
        ->assertNoJavaScriptErrors();

    $page->refresh()->assertValue('#ration-norm-category', 'brebis_laitiere')
        ->assertValue('#bpr-min', '2.5')->assertValue('#bpr-max', '5')
        ->select('#ration-norm-category', 'chevre_laitiere')->assertValue('#bpr-min', '')
        ->select('#ration-norm-category', 'brebis_laitiere')
        ->click('Réinitialiser')->click('Enregistrer')->assertSee('Enregistré.')
        ->select('#ration-norm-category', 'vache_laitiere')->assertValue('#bpr-min', '1.25')
        ->assertNoJavaScriptErrors();
    expect($user->fresh()->normes_personnalisees['categories'])->toBe([
        'vache_laitiere' => ['bpr' => ['min' => 1.25, 'max' => null]],
    ]);
});
