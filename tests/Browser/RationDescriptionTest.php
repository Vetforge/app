<?php

use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;

/**
 * Le poids vif proposé par le formulaire de description doit suivre l'espèce sélectionnée
 * (watcher frontend), et non rester figé sur le 650 kg de la vache.
 */
test('changing species updates the suggested live weight on the description form', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id,
        'categorie_animal' => 'vache_laitiere',
        'poids_vif' => null,
    ]);

    $this->actingAs($user);

    visit(route('plans.rations.description', [$plan, $ration], absolute: false))
        ->assertNoJavaScriptErrors()
        ->assertValue('#poids_vif', '650')                // défaut vache laitière
        ->select('#categorie_animal', 'chevre_laitiere')
        ->assertValue('#poids_vif', '60')                 // défaut chèvre laitière
        ->select('#categorie_animal', 'brebis_laitiere')
        ->assertValue('#poids_vif', '70')                 // défaut brebis
        ->select('#categorie_animal', 'agneau_croissance')
        ->assertValue('#poids_vif', '30');                // défaut agneau
});
