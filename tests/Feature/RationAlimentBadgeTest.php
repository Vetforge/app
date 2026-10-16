<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('includes INRA codes in the available aliments', function (string $route) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    $reference = Aliment::factory()->systemique()->create([
        'libelle0' => 'A Foin INRA', 'code_inra' => '1234', 'usage_aliment' => 1,
    ]);
    $personal = Aliment::factory()->create([
        'user_id' => $user->id, 'libelle0' => 'B Foin ferme', 'code_inra' => null, 'usage_aliment' => 1,
    ]);

    $this->actingAs($user)->get(route($route, [$plan, $ration]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('aliments_disponibles', 2)
            ->where('aliments_disponibles.0.id', $reference->id)
            ->where('aliments_disponibles.0.code_inra', '1234')
            ->where('aliments_disponibles.1.id', $personal->id)
            ->where('aliments_disponibles.1.code_inra', null)
        );
})->with(['plans.rations.composition', 'plans.rations.resultats']);
