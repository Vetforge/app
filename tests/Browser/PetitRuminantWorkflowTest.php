<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;

test('a small ruminant ration can be created, configured and fed through the browser', function (string $category, string $label, array $fields, string $unit, string $fillUnit) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    Aliment::factory()->systemique()->create([
        'usage_aliment' => 1, 'type' => 'fourrage', 'libelle0' => 'Foin petits ruminants', 'libelle1' => null,
        'ms' => 85, 'ufl' => 0.8, 'ufv' => 0.9, 'pdi' => 100, 'uel' => 1.1, 'uem' => 1.2,
        'eb' => null, 'mo' => 900, 'mat' => 150, 'd_mo' => 65,
    ]);
    $this->actingAs($user);
    $page = visit(route('plans.show', $plan, absolute: false))
        ->click('Nouvelle ration')->click(str_starts_with($category, 'chevre') ? 'Caprins' : 'Ovins')
        ->click('button:has-text('.json_encode($label, JSON_UNESCAPED_UNICODE).')')->fill('#nom', 'Lot petits ruminants')
        ->click('button[type=submit]')->assertValue('#categorie_animal', $category)
        ->select('#race', $category === 'chevre_laitiere' || $category === 'chevrette_croissance' ? 'alpine' : 'lacaune');
    foreach ($fields as $field => $value) {
        if ($field === 'sexe') {
            $page->select('#'.$field, $value);
        } else {
            $page->fill('#'.$field, (string) $value);
        }
    }
    if (isset($fields['gmq'])) {
        $page->assertDontSee('Lactation & Gestation')
            ->assertNotPresent('#jours_lactation');
        if ($category === 'chevrette_croissance') {
            $page->assertVisible('#jours_gestation');
        } else {
            $page->assertNotPresent('#jours_gestation');
        }
    } else {
        $page->assertVisible('#jours_lactation')->assertVisible('#jours_gestation');
    }
    $page->click('button[type=submit]')->assertSee('Composition et résultats')
        ->assertSee($unit)->assertSee($fillUnit)->assertNoJavaScriptErrors();
    $ration = $plan->rations()->sole();
    $page->assertPathIs(route('plans.rations.composition', [$plan, $ration], absolute: false))
        ->click('button:text-is("Ajouter")')->click('[aria-label="Aliment à ajouter à la ration"]')
        ->keys('input[role=combobox]', ['ArrowDown', 'Enter'])
        ->assertAttribute('[aria-label="Aliment à ajouter à la ration"]', 'aria-expanded', 'false')
        ->assertNotPresent('[data-slot=content]')
        ->fill('input[aria-label^="Quantité de l’aliment à ajouter"]', '1.5')
        ->assertValue('input[aria-label^="Quantité de l’aliment à ajouter"]', '1.5')
        ->click('#composition > section:first-child > div:nth-child(2) button:text-is("Ajouter")')
        ->assertVisible('[aria-label="Retirer Foin petits ruminants de la ration"]')
        ->assertNoJavaScriptErrors();
    expect((float) $ration->rationAliments()->sole()->quantite)->toBe(1.5);
    visit(route('plans.rations.resultats', [$plan, $ration], absolute: false))
        ->assertSee('Apports vs besoins')->assertSee($unit)->assertNoJavaScriptErrors()
        ->click('Paramètres animal')->assertValue('#categorie_animal', $category);
    if (isset($fields['jours_lactation'])) {
        visit(route('plans.rations.description', [$plan, $ration], absolute: false))
            ->fill('#jours_lactation', '0')->fill('#jours_gestation', $category === 'chevre_laitiere' ? '140' : '126')
            ->fill('#nombre_jeunes', '2')->fill('#poids_portee', '8')
            ->click('button[type=submit]')->assertSee('Composition et résultats')->assertNoJavaScriptErrors();
        expect($ration->fresh()->lait_objectif)->toBeNull();
        visit(route('plans.rations.description', [$plan, $ration], absolute: false))
            ->fill('#jours_gestation', '0')->click('button[type=submit]')
            ->assertSee('Composition et résultats')->assertNoJavaScriptErrors();
    }
})->with([
    ['brebis_laitiere', 'Brebis laitière', ['poids_vif' => 70, 'parite' => 2, 'jours_lactation' => 60, 'lait_objectif' => 2.5], 'UFL', 'UEM'],
    ['brebis_allaitante', 'Brebis allaitante (suitée)', ['poids_vif' => 65, 'parite' => 2, 'jours_lactation' => 14, 'nombre_jeunes' => 2, 'gmq_portee' => 357], 'UFL', 'UEM'],
    ['agneau_croissance', "Agneau à l'engraissement", ['poids_vif' => 30, 'age_jours' => 100, 'sexe' => 'male', 'poids_adulte' => 70, 'gmq' => 250], 'UFV', 'kg MS'],
    ['chevre_laitiere', 'Chèvre laitière', ['poids_vif' => 60, 'parite' => 2, 'jours_lactation' => 60, 'lait_objectif' => 3.5], 'UFL', 'UEL'],
    ['chevrette_croissance', 'Chevrette en croissance', ['poids_vif' => 27.7, 'age_jours' => 152, 'sexe' => 'femelle', 'poids_adulte' => 70, 'gmq' => 127], 'UFL', 'UEL'],
]);

test('missing small ruminant parameters are visible after opening results', function (string $category, array $parameters, string $field) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id, 'categorie_animal' => $category,
        'race' => 'autre', 'poids_vif' => 30, 'parite' => 2, 'jours_lactation' => 21,
        ...$parameters,
    ]);
    $this->actingAs($user);
    visit(route('plans.rations.resultats', [$plan, $ration], absolute: false))
        ->assertPathIs(route('plans.rations.description', [$plan, $ration], absolute: false))
        ->assertVisible('#'.$field.'-error')->assertAttribute('#'.$field, 'aria-invalid', 'true')->assertNoJavaScriptErrors();
})->with([
    ['brebis_laitiere', ['lait_objectif' => null], 'lait_objectif'],
    ['brebis_allaitante', ['gmq_portee' => null, 'nombre_jeunes' => 2], 'gmq_portee'],
    ['chevrette_croissance', ['sexe' => 'femelle', 'poids_adulte' => 70, 'gmq' => 127, 'age_jours' => null], 'age_jours'],
]);
