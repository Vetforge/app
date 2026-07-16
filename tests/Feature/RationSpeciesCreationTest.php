<?php

use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;
use App\Services\RationCalculator;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function planForUser(string $inra = '2018'): array
{
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create([
        'user_id' => $user->id,
        'inra' => $inra,
    ]);

    return [$user, $plan];
}

test('the creation page exposes species options grouped by species', function () {
    [$user, $plan] = planForUser();

    actingAs($user)
        ->get(route('plans.rations.create', $plan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rations/Create')
            ->has('categorie_options', 3)
            ->where('categorie_options.0.espece', 'bovin')
        );
});

test('creating a ration stores the canonical category value', function () {
    [$user, $plan] = planForUser('2018');

    actingAs($user)
        ->post(route('plans.rations.store', $plan), [
            'nom' => 'Lot laitières',
            'categorie_animal' => 'vache_laitiere',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('rations', [
        'plan_rationnement_id' => $plan->id,
        'nom' => 'Lot laitières',
        'categorie_animal' => 'vache_laitiere',
    ]);
});

test('creating a ration normalizes a legacy category label', function () {
    [$user, $plan] = planForUser('2018');

    actingAs($user)
        ->post(route('plans.rations.store', $plan), [
            'nom' => 'Lot allaitantes',
            'categorie_animal' => 'Vache allaitante',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('rations', [
        'nom' => 'Lot allaitantes',
        'categorie_animal' => 'vache_allaitante',
    ]);
});

test('creating a ration requires a category', function () {
    [$user, $plan] = planForUser('2018');

    actingAs($user)
        ->post(route('plans.rations.store', $plan), ['nom' => 'Sans catégorie'])
        ->assertSessionHasErrors('categorie_animal');
});

test('non-cow categories are rejected on an INRA 2007 plan', function () {
    [$user, $plan] = planForUser('2007');

    actingAs($user)
        ->post(route('plans.rations.store', $plan), [
            'nom' => 'Chèvres sur plan 2007',
            'categorie_animal' => 'chevre_laitiere',
        ])
        ->assertSessionHasErrors('categorie_animal');

    $this->assertDatabaseMissing('rations', ['nom' => 'Chèvres sur plan 2007']);
});

test('suckler cows are allowed on an INRA 2007 plan', function () {
    [$user, $plan] = planForUser('2007');

    actingAs($user)
        ->post(route('plans.rations.store', $plan), [
            'nom' => 'Allaitantes 2007',
            'categorie_animal' => 'vache_allaitante',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('rations', [
        'nom' => 'Allaitantes 2007',
        'categorie_animal' => 'vache_allaitante',
    ]);
});

test('updating the description validates the enum and persists species fields', function () {
    [$user, $plan] = planForUser('2018');
    $ration = Ration::factory()->vacheLaitiere()->create(['plan_rationnement_id' => $plan->id]);

    actingAs($user)
        ->put(route('plans.rations.description.update', [$plan, $ration]), [
            'nom' => 'Chèvres 70 kg',
            'categorie_animal' => 'chevre_laitiere',
            'poids_vif' => 70,
            'race' => 'alpine',
            'parite' => 2,
            'lait_objectif' => 3.5,
            'mfc' => 35,
            'mpc' => 31,
            'jours_lactation' => 60,
            'nombre_jeunes' => 2,
        ])
        ->assertRedirect();

    $ration->refresh();
    expect($ration->categorie_animal)->toBe('chevre_laitiere');
    expect($ration->mfc)->toBe(35.0);
    expect($ration->mpc)->toBe(31.0);
    expect($ration->jours_lactation)->toBe(60);
    expect($ration->nombre_jeunes)->toBe(2);
});

test('updating the description normalizes a legacy category value', function () {
    [$user, $plan] = planForUser('2018');
    $ration = Ration::factory()->vacheLaitiere()->create(['plan_rationnement_id' => $plan->id]);

    actingAs($user)
        ->put(route('plans.rations.description.update', [$plan, $ration]), [
            'nom' => $ration->nom,
            'categorie_animal' => 'vacheLaitiere',
            'poids_vif' => $ration->poids_vif,
            'race' => 'prim_holstein',
        ])
        ->assertRedirect();

    expect($ration->refresh()->categorie_animal)->toBe('vache_laitiere');
});

test('updating the description requires a race', function () {
    [$user, $plan] = planForUser('2018');
    $ration = Ration::factory()->vacheLaitiere()->create(['plan_rationnement_id' => $plan->id]);

    actingAs($user)
        ->put(route('plans.rations.description.update', [$plan, $ration]), [
            'nom' => $ration->nom,
            'categorie_animal' => 'vache_laitiere',
            'poids_vif' => 650,
        ])
        ->assertSessionHasErrors('race');
});

test('the caprine model accepts alpine, saanen and autre but rejects any other race', function () {
    [$user, $plan] = planForUser('2018');
    $ration = Ration::factory()->vacheLaitiere()->create(['plan_rationnement_id' => $plan->id]);

    $payload = fn (string $race): array => [
        'nom' => 'Chèvres 70 kg',
        'categorie_animal' => 'chevre_laitiere',
        'poids_vif' => 70,
        'race' => $race,
        'parite' => 2,
        'lait_objectif' => 3.5,
        'jours_lactation' => 60,
        'nombre_jeunes' => 2,
    ];

    actingAs($user)
        ->put(route('plans.rations.description.update', [$plan, $ration]), $payload('autre'))
        ->assertSessionHasNoErrors();

    expect($ration->refresh()->race)->toBe('autre');

    actingAs($user)
        ->put(route('plans.rations.description.update', [$plan, $ration]), $payload('lacaune'))
        ->assertSessionHasErrors('race');
});

test('a generic race is accepted outside the caprine model', function () {
    [$user, $plan] = planForUser('2018');
    $ration = Ration::factory()->vacheLaitiere()->create(['plan_rationnement_id' => $plan->id]);

    actingAs($user)
        ->put(route('plans.rations.description.update', [$plan, $ration]), [
            'nom' => $ration->nom,
            'categorie_animal' => 'vache_laitiere',
            'poids_vif' => 650,
            'race' => 'autre',
            'lait_objectif' => 28,
            'mois_lactation' => 3,
        ])
        ->assertSessionHasNoErrors();

    expect($ration->refresh()->race)->toBe('autre');
});

test('a suckler cow ration never exposes an expected milk production', function () {
    $plan = PlanRationnement::factory()->create(['inra' => '2018']);
    $ration = Ration::factory()->vacheAllaitante()->create([
        'plan_rationnement_id' => $plan->id,
        'lait_objectif' => 8,
        'poids_vif' => 700,
        'mois_lactation' => 2.0,
    ]);

    $ration->load(['planRationnement', 'rationAliments.aliment', 'melanges.melangeAliments.aliment']);
    $resultats = RationCalculator::calculer($ration);

    expect($resultats['impacts'])->not->toHaveKey('production_lait_attendue');
});
