<?php

use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;

test('physiological correction inputs are available and can be saved in the browser', function (string $category, array $parameters, array $fields) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create(array_merge([
        'plan_rationnement_id' => $plan->id, 'categorie_animal' => $category,
        'race' => 'alpine', 'poids_vif' => 40, 'parite' => 1, 'effectif' => 1,
        'nec' => 3,
        'lait_objectif' => 0, 'mois_lactation' => 0, 'mois_gestation' => 0,
        'jours_lactation' => 0, 'jours_gestation' => 0,
    ], $parameters));
    $this->actingAs($user);
    $page = visit(route('plans.rations.description', [$plan, $ration], absolute: false));
    foreach ($fields as $field => $value) {
        $page->assertVisible('#'.$field)->fill('#'.$field, (string) $value);
    }
    $page->assertSee('2e édition anglaise (2019)')->click('button[type=submit]')
        ->assertSee('Composition et résultats')->assertNoJavaScriptErrors();
    $ration->refresh();
    foreach ($fields as $field => $value) {
        expect((float) $ration->$field)->toEqualWithDelta((float) $value, 1e-8);
    }
})->with([
    'vache laitière' => ['vache_laitiere', ['poids_vif' => 600, 'race' => 'holstein', 'pourcentage_primipare' => 100], ['age_mois' => 24, 'mois_lactation' => 3, 'lait_objectif' => 25]],
    'brebis laitière à la limite de NEC' => ['brebis_laitiere', ['race' => 'lacaune', 'nec' => 4.5, 'jours_gestation' => 60, 'nombre_jeunes' => 2, 'poids_portee' => 8], ['gmq' => 100, 'poids_adulte' => 75, 'variation_nec' => 0.5, 'duree_variation_nec_jours' => 60]],
    'chèvre laitière' => ['chevre_laitiere', ['jours_lactation' => 60, 'lait_objectif' => 3], ['age_mois' => 17, 'ecart_variation_reserve' => -0.1]],
    'chevrette en croissance' => ['chevrette_croissance', ['age_jours' => 300, 'gmq' => 85, 'poids_adulte' => 76.6, 'sexe' => 'femelle'], ['jours_gestation' => 120, 'nombre_jeunes' => 2, 'poids_portee' => 8]],
]);

test('an ewe body condition target above five is rejected and can be corrected', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id, 'categorie_animal' => 'brebis_laitiere',
        'race' => 'lacaune', 'poids_vif' => 40, 'parite' => 1, 'effectif' => 1,
        'nec' => 4.8, 'variation_nec' => null, 'duree_variation_nec_jours' => 60,
        'gmq' => 100, 'poids_adulte' => 75, 'jours_gestation' => 60,
        'jours_lactation' => 0, 'nombre_jeunes' => 2, 'poids_portee' => 8,
    ]);
    $this->actingAs($user);
    $page = visit(route('plans.rations.description', [$plan, $ration], absolute: false));

    $page->fill('#variation_nec', '0.5')->click('button[type=submit]')
        ->assertSee('La NEC visée doit rester entre 0 et 5.')
        ->assertPathIs(route('plans.rations.description', [$plan, $ration], absolute: false))
        ->assertValue('#variation_nec', '0.5');
    expect($ration->fresh()->variation_nec)->toBeNull();

    $page->fill('#nec', '4.5')->click('button[type=submit]')
        ->assertSee('Composition et résultats')->assertNoJavaScriptErrors();
    expect((float) $ration->fresh()->nec)->toBe(4.5)
        ->and($ration->fresh()->variation_nec)->toBe(0.5);
});
