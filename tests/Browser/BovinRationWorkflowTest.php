<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;

test('a bovine ration can be created, configured and fed through the browser', function (string $category, string $label, int $reference, int $gmq, string $unit) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    Aliment::factory()->systemique()->create([
        'usage_aliment' => 1, 'type' => 'fourrage', 'libelle0' => 'Foin de référence bovine',
        'libelle1' => null, 'ms' => 85, 'ufl' => 0.8, 'ufv' => 0.7, 'pdi' => 90,
        'ueb' => 1.1, 'eb' => null, 'mo' => 900, 'd_mo' => 65, 'mat' => 120,
    ]);
    $this->actingAs($user);

    $page = visit(route('plans.show', $plan, absolute: false))
        ->click('Nouvelle ration')->click($label)->fill('#nom', 'Lot bovins test')
        ->click('button[type=submit]')->assertNoJavaScriptErrors()
        ->assertValue('#categorie_animal', $category)
        ->assertDontSee('Lactation & Gestation')
        ->assertNotPresent('#mois_lactation')->assertNotPresent('#mois_gestation')
        ->assertNotPresent('#poids_veau_naissance')
        ->select('#race', 'autre')->fill('#poids_vif', '400')
        ->fill('#gmq', (string) $gmq)->select('#reference_bovine', (string) $reference)
        ->click('button[type=submit]')->assertSee('Composition et résultats')
        ->assertSee($unit)->assertNoJavaScriptErrors();

    $ration = $plan->rations()->sole();
    $page->assertPathIs(route('plans.rations.composition', [$plan, $ration], absolute: false))
        ->click('button:text-is("Ajouter")')->click('[aria-label="Aliment à ajouter à la ration"]')
        ->keys('input[role=combobox]', ['ArrowDown', 'Enter'])
        ->assertAttribute('[aria-label="Aliment à ajouter à la ration"]', 'aria-expanded', 'false')
        ->assertNotPresent('[data-slot=content]')
        ->fill('input[aria-label^="Quantité de l’aliment à ajouter"]', '6')
        ->assertValue('input[aria-label^="Quantité de l’aliment à ajouter"]', '6')
        ->click('#composition > section:first-child > div:nth-child(2) button:text-is("Ajouter")')
        ->assertVisible('[aria-label="Retirer Foin de référence bovine de la ration"]')
        ->assertNoJavaScriptErrors();
    expect((float) $ration->rationAliments()->sole()->quantite)->toBe(6.0);

    visit(route('plans.rations.resultats', [$plan, $ration], absolute: false))
        ->assertSee('Apports vs besoins')->assertSee($unit)->assertNoJavaScriptErrors()
        ->click('Paramètres animal')->assertValue('#reference_bovine', (string) $reference);
})->with([
    ['bovin_croissance', 'Bovin en croissance', 10, 800, 'UFL'],
    ['bovin_engraissement', "Bovin à l'engraissement", 1, 1400, 'UFV'],
]);

test('opening incomplete bovine results shows the missing fields on the animal form', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id, 'categorie_animal' => 'bovin_croissance',
        'poids_vif' => 400, 'race' => 'autre', 'reference_bovine' => null, 'gmq' => null,
    ]);
    $this->actingAs($user);

    visit(route('plans.rations.resultats', [$plan, $ration], absolute: false))
        ->assertPathIs(route('plans.rations.description', [$plan, $ration], absolute: false))
        ->assertVisible('#reference_bovine-error')->assertVisible('#gmq-error')
        ->assertAttribute('#reference_bovine', 'aria-invalid', 'true')
        ->assertNoJavaScriptErrors();
});

test('changing bovine mode clears an incompatible reference and accepts the new parameters', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id, 'categorie_animal' => 'bovin_croissance',
        'poids_vif' => 400, 'race' => 'charolaise', 'reference_bovine' => 10, 'gmq' => 800,
        'nec' => 1.9, 'temperature_ambiante' => 15.4,
    ]);
    $this->actingAs($user);

    $page = visit(route('plans.rations.description', [$plan, $ration], absolute: false))
        ->assertNotPresent('#mois_lactation')->assertNotPresent('#mois_gestation')
        ->assertNotPresent('#poids_veau_naissance')
        ->select('#categorie_animal', 'bovin_engraissement')
        ->assertNotPresent('#mois_lactation')->assertNotPresent('#mois_gestation')
        ->assertNotPresent('#poids_veau_naissance')
        ->assertValue('#reference_bovine', 'Sélectionner')
        ->select('#reference_bovine', '1')->fill('#gmq', '1400')
        ->click('button[type=submit]')->assertSee('Composition et résultats')->assertSee('UFV')
        ->assertNoJavaScriptErrors()
        ->click('Paramètres animal')->assertValue('#reference_bovine', '1')
        ->assertValue('#nec', '1.9')->fill('#nec', '2.75')
        ->select('#categorie_animal', 'bovin_croissance')
        ->assertValue('#reference_bovine', 'Sélectionner')
        ->select('#reference_bovine', '10')->fill('#gmq', '800')
        ->click('button[type=submit]')->assertSee('Composition et résultats')->assertSee('UFL')
        ->assertNoJavaScriptErrors();
    $page->assertPathIs(route('plans.rations.composition', [$plan, $ration], absolute: false));
    expect((float) $ration->fresh()->nec)->toBe(2.75);
    expect((float) $ration->fresh()->temperature_ambiante)->toBe(15.4);

    visit(route('plans.rations.description', [$plan, $ration], absolute: false))
        ->select('#categorie_animal', 'vache_allaitante')
        ->assertVisible('#mois_lactation')->assertVisible('#mois_gestation')
        ->assertVisible('#poids_veau_naissance')
        ->select('#categorie_animal', 'bovin_croissance')
        ->assertNotPresent('#mois_lactation')->assertNotPresent('#mois_gestation')
        ->assertNotPresent('#poids_veau_naissance')
        ->select('#categorie_animal', 'vache_laitiere')
        ->assertVisible('#mois_lactation')->assertVisible('#mois_gestation')
        ->assertVisible('#poids_veau_naissance')->assertNoJavaScriptErrors();
});
