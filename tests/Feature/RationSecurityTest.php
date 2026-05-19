<?php

use App\Models\Aliment;
use App\Models\Melange;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Models\User;

// --- Scoped bindings: ration isolation ---

it('returns 404 when accessing another users ration via own plan', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $userPlan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $otherPlan = PlanRationnement::factory()->create(['user_id' => $otherUser->id]);
    $otherRation = Ration::factory()->create(['plan_rationnement_id' => $otherPlan->id]);

    $this->actingAs($user)
        ->get(route('plans.rations.composition', [$userPlan, $otherRation]))
        ->assertNotFound();
});

it('returns 404 when trying to modify another users ration via own plan', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $userPlan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $otherPlan = PlanRationnement::factory()->create(['user_id' => $otherUser->id]);
    $otherRation = Ration::factory()->create(['plan_rationnement_id' => $otherPlan->id]);

    $this->actingAs($user)
        ->delete(route('plans.rations.destroy', [$userPlan, $otherRation]))
        ->assertNotFound();
});

it('returns 404 when accessing another users rationAliment via own plan and ration', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $userPlan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $userRation = Ration::factory()->create(['plan_rationnement_id' => $userPlan->id]);
    $aliment = Aliment::factory()->systemique()->create();

    $otherPlan = PlanRationnement::factory()->create(['user_id' => $otherUser->id]);
    $otherRation = Ration::factory()->create(['plan_rationnement_id' => $otherPlan->id]);
    $otherRationAliment = RationAliment::create([
        'ration_id' => $otherRation->id,
        'aliment_id' => $aliment->id,
        'quantite' => 5,
        'is_volonte' => false,
        'is_mb' => false,
        'ordre' => 1,
    ]);

    $this->actingAs($user)
        ->delete(route('plans.rations.aliments.remove', [$userPlan, $userRation, $otherRationAliment]))
        ->assertNotFound();
});

it('returns 404 when accessing another users melange via own plan and ration', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $userPlan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $userRation = Ration::factory()->create(['plan_rationnement_id' => $userPlan->id]);

    $otherPlan = PlanRationnement::factory()->create(['user_id' => $otherUser->id]);
    $otherRation = Ration::factory()->create(['plan_rationnement_id' => $otherPlan->id]);
    $otherMelange = Melange::create([
        'ration_id' => $otherRation->id,
        'nom' => 'Mélange adverse',
        'ordre' => 1,
    ]);

    $this->actingAs($user)
        ->delete(route('plans.rations.melanges.destroy', [$userPlan, $userRation, $otherMelange]))
        ->assertNotFound();
});

// --- addAliment: aliment source isolation ---

it('forbids adding another users private aliment to a ration', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $plan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    $privateAliment = Aliment::factory()->create([
        'user_id' => $otherUser->id,
        'code_inra' => null,
    ]);

    $this->actingAs($user)
        ->post(route('plans.rations.aliments.add', [$plan, $ration]), [
            'aliment_id' => $privateAliment->id,
            'quantite' => 5,
            'is_volonte' => false,
            'is_mb' => false,
        ])
        ->assertForbidden();
});

it('allows adding an INRA aliment to a ration', function () {
    $user = User::factory()->create();

    $plan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    $inraAliment = Aliment::factory()->systemique()->create([
        'code_inra' => '0133',
        'libelle0' => 'Maïs ensilage',
    ]);

    $this->actingAs($user)
        ->post(route('plans.rations.aliments.add', [$plan, $ration]), [
            'aliment_id' => $inraAliment->id,
            'quantite' => 10,
            'is_volonte' => false,
            'is_mb' => false,
        ])
        ->assertRedirect();

    expect(Aliment::query()->where('user_id', $user->id)->where('usage_aliment', 2)->exists())->toBeTrue();
});

it('forbids adding another users private aliment to a melange', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $plan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    $melange = Melange::create(['ration_id' => $ration->id, 'nom' => 'PMR', 'ordre' => 1]);
    $privateAliment = Aliment::factory()->create([
        'user_id' => $otherUser->id,
        'code_inra' => null,
    ]);

    $this->actingAs($user)
        ->post(route('plans.rations.melanges.aliments.add', [$plan, $ration, $melange]), [
            'aliment_id' => $privateAliment->id,
            'quantite' => 5,
            'is_mb' => false,
        ])
        ->assertForbidden();
});

it('allows adding an INRA aliment to a melange', function () {
    $user = User::factory()->create();

    $plan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    $melange = Melange::create(['ration_id' => $ration->id, 'nom' => 'PMR', 'ordre' => 1]);
    $inraAliment = Aliment::factory()->systemique()->create([
        'code_inra' => '0133',
        'libelle0' => 'Maïs ensilage',
    ]);

    $this->actingAs($user)
        ->post(route('plans.rations.melanges.aliments.add', [$plan, $ration, $melange]), [
            'aliment_id' => $inraAliment->id,
            'quantite' => 8,
            'is_mb' => false,
        ])
        ->assertRedirect();

    expect(Aliment::query()->where('user_id', $user->id)->where('usage_aliment', 2)->exists())->toBeTrue();
});
