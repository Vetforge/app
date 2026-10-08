<?php

use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;

test('the plan duplication button opens the new ration for editing', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $ration = Ration::factory()->vacheLaitiere()->create(['plan_rationnement_id' => $plan->id, 'nom' => 'Ration laitières', 'poids_vif' => 650]);
    $this->actingAs($user);

    $page = visit(route('plans.show', $plan, absolute: false))
        ->assertNoJavaScriptErrors()
        ->click('button[aria-label="Dupliquer la ration Ration laitières"]')
        ->assertValue('#nom', 'Ration laitières')
        ->assertValue('#poids_vif', '650')
        ->assertNoJavaScriptErrors();

    $copy = $plan->rations()->whereKeyNot($ration->id)->sole();
    $page->assertPathIs(route('plans.rations.description', [$plan, $copy], absolute: false));
    expect($copy->nom)->toBe($ration->nom)->and($plan->rations()->count())->toBe(2);
});
