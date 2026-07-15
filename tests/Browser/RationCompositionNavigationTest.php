<?php

use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;

/**
 * La page composition doit offrir un retour direct vers la page de description
 * (paramètres animal) de la ration.
 */
test('the composition page links back to the ration description page', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id,
        'categorie_animal' => 'vache_laitiere',
    ]);

    $this->actingAs($user);

    visit(route('plans.rations.composition', [$plan, $ration], absolute: false))
        ->assertNoJavaScriptErrors()
        ->assertSee('Paramètres animal')
        ->click('Paramètres animal')
        ->assertPathIs(route('plans.rations.description', [$plan, $ration], absolute: false));
});
