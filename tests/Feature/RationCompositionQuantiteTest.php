<?php

use App\Models\Aliment;
use App\Models\Melange;
use App\Models\MelangeAliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Models\User;

/**
 * @return array{0: PlanRationnement, 1: Ration, 2: RationAliment, 3: Aliment}
 */
function createRationWithAliment(User $user): array
{
    $plan = PlanRationnement::factory()->create([
        'user_id' => $user->id,
        'inra' => '2018',
    ]);

    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id,
        'categorie_animal' => 'chevreLaitiere',
        'poids_vif' => 70,
    ]);

    $aliment = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
    ]);

    $rationAliment = RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $aliment->id,
        'quantite' => 4,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 1,
    ]);

    return [$plan, $ration, $rationAliment, $aliment];
}

it('accepts a zero quantity when zeroing out a ration aliment line', function () {
    $user = User::factory()->create();
    [$plan, $ration, $rationAliment] = createRationWithAliment($user);

    $this->actingAs($user)
        ->put(route('plans.rations.aliments.update', [$plan, $ration, $rationAliment]), [
            'quantite' => 0,
            'is_volonte' => false,
            'is_mb' => false,
        ])
        ->assertSessionHasNoErrors();

    expect((float) $rationAliment->fresh()->quantite)->toBe(0.0);
});

it('rejects a negative quantity on a ration aliment line', function () {
    $user = User::factory()->create();
    [$plan, $ration, $rationAliment] = createRationWithAliment($user);

    $this->actingAs($user)
        ->put(route('plans.rations.aliments.update', [$plan, $ration, $rationAliment]), [
            'quantite' => -1,
            'is_volonte' => false,
            'is_mb' => false,
        ])
        ->assertSessionHasErrors('quantite');

    expect((float) $rationAliment->fresh()->quantite)->toBe(4.0);
});

it('accepts a zero quantity when zeroing out an aliment inside a melange', function () {
    $user = User::factory()->create();
    [$plan, $ration, , $aliment] = createRationWithAliment($user);

    $melange = Melange::create([
        'ration_id' => $ration->id,
        'nom' => 'Mélange test',
        'quantite' => 2,
        'is_volonte' => false,
        'is_mb' => false,
        'ordre' => 1,
    ]);

    $melangeAliment = MelangeAliment::create([
        'melange_id' => $melange->id,
        'aliment_id' => $aliment->id,
        'quantite' => 3,
        'is_mb' => false,
        'ordre' => 1,
    ]);

    $this->actingAs($user)
        ->put(route('plans.rations.melanges.aliments.update', [$plan, $ration, $melange, $melangeAliment]), [
            'quantite' => 0,
            'is_mb' => false,
        ])
        ->assertSessionHasNoErrors();

    expect((float) $melangeAliment->fresh()->quantite)->toBe(0.0);
});
