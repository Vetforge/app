<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;
use App\Services\Equations2007\Besoin as Besoin2007;
use App\Services\Equations2018\Apport;
use App\Services\Equations2018\Besoin;
use App\Services\Equations2018\Impact;
use App\Services\RationCalculator;
use Inertia\Testing\AssertableInertia as Assert;

function correctedScientificRation(array $parameters = [], float $quantity = 16): Ration
{
    $ration = Ration::factory()->create(array_merge([
        'plan_rationnement_id' => PlanRationnement::factory()->create(['inra' => '2018'])->id,
        'categorie_animal' => 'vache_laitiere', 'poids_vif' => 650, 'race' => 'holstein',
        'effectif' => 1, 'parite' => 2, 'pourcentage_primipare' => 0, 'age_mois' => null,
        'nec' => 2.5, 'nec_velage' => null, 'ecart_variation_reserve' => 0,
        'mois_lactation' => 3, 'mois_gestation' => 0, 'jours_lactation' => 0, 'jours_gestation' => 0,
        'lait_objectif' => 30, 'lait_potentiel' => null, 'lait_potentiel305j' => null,
        'tb_annuel' => 40, 'tp_annuel' => 31, 'gmq' => 0, 'activite' => 'stabulation', 'temperature_ambiante' => 15,
    ], $parameters));
    if ($quantity > 0) {
        $feed = Aliment::factory()->systemique()->create([
            'type' => 'fourrage', 'ms' => 30, 'ufl' => 0.85, 'pdi' => 100, 'uel' => 1,
            'ufv' => 0.85, 'ueb' => 1, 'uem' => 1, 'eb' => null, 'mo' => 900, 'd_mo' => 65,
            'mat' => 150, 'lys_di' => 6.7, 'met_di' => 1.9,
        ]);
        $ration->rationAliments()->create(['aliment_id' => $feed->id, 'quantite' => $quantity, 'is_mb' => false, 'is_volonte' => false, 'ordre' => 1]);
    }

    return $ration->load(['planRationnement', 'rationAliments.aliment', 'melanges.melangeAliments.aliment']);
}

test('dairy substitution reproduces the integral in equation 17.37', function () {
    // Independently evaluated published equation: S0=.5, PI=.6, S=1.15/.85.
    expect(Apport::calculerTSg(1, 0.5, 0.85, 1.15, 100, 1, 0.5, 0.3))
        ->toEqualWithDelta(0.5055430589, 1e-9);
    expect(Apport::calculerTSg(1, 0.5, 0.85, 1.15, 100, 1, 0.5, 0))
        ->toEqualWithDelta(0.5009789499815519, 1e-9);
});

test('an empty dairy ration has positive intake and its first ad libitum forage is solved', function () {
    $user = User::factory()->create();
    $ration = correctedScientificRation([], 0);
    $ration->planRationnement->update(['user_id' => $user->id]);
    expect(Besoin::calculerCapaciteIngestion($ration))->toBeGreaterThan(15);
    $hay = Aliment::factory()->systemique()->create(['type' => 'fourrage', 'ms' => 85, 'ufl' => 0.85, 'pdi' => 100, 'uel' => 1, 'eb' => null]);
    $line = $ration->rationAliments()->create(['aliment_id' => $hay->id, 'quantite' => 0, 'is_mb' => false, 'is_volonte' => true, 'ordre' => 1]);
    $this->actingAs($user)->get(route('plans.rations.composition', [$ration->planRationnement, $ration]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('resultats.bilans.ue', fn ($value) => abs($value) <= 0.01));
    expect((float) $line->fresh()->quantite)->toBeGreaterThan(15);
});

test('a dry cow is not assigned the early lactation intake depression', function () {
    $ration = correctedScientificRation(['mois_lactation' => 0, 'mois_gestation' => 8, 'lait_objectif' => 0, 'lait_potentiel305j' => 9000]);
    $gestationIndex = 0.8 + 0.2 * (1 - exp(-0.25 * (40 - 8 * 4.348)));
    $maturity = -0.1 + 1.1 * (1 - exp(-0.08 * 60));
    $proteinIndex = 0.91 + 0.115 / (1 + exp(0.13 * (90 - 100 / 0.85)));
    expect(Besoin::calculerCapaciteIngestion($ration))->toEqualWithDelta(15 * $gestationIndex * $maturity * $proteinIndex, 1e-8);
});

test('dairy PDI efficiency remains physical when intake cannot cover obligatory urine losses', function (float $quantity) {
    $ration = correctedScientificRation([], $quantity);
    $efficiency = Apport::calculerEffPDI($ration);
    expect($efficiency)->toBeGreaterThanOrEqual(0.2)->toBeLessThanOrEqual(1);
    expect(Besoin::calculerBesoinPDI_PL($ration))->toBeGreaterThan(0);
    $result = RationCalculator::calculer($ration);
    array_walk_recursive($result, function ($value) {
        if (is_float($value)) {
            expect(is_finite($value))->toBeTrue();
        }
    });
    expect($result['bilans']['pdi'])->toBeLessThan(0);
})->with([0.0, 0.1, 2.0]);

test('dairy growth uses actual age in equations 17.10 and 17.18 with calcium and phosphorus', function (float $age, float $uf, float $protein, float $ca, float $p) {
    $ration = correctedScientificRation(['age_mois' => $age, 'parite' => 1]);
    $adult = correctedScientificRation(['age_mois' => 60, 'parite' => 2]);
    expect(Besoin::calculerBesoinUF_gain($ration))->toEqualWithDelta($uf, 1e-8);
    expect(Besoin::calculerBesoinPDI_gain($ration) * Apport::calculerEffPDI($ration))->toEqualWithDelta($protein, 1e-8);
    expect(Besoin::calculerBesoinCaabs($ration) - Besoin::calculerBesoinCaabs($adult))->toEqualWithDelta($ca, 1e-8);
    expect(Besoin::calculerBesoinPabs($ration) - Besoin::calculerBesoinPabs($adult))->toEqualWithDelta($p, 1e-8);
})->with([[24, 1.292, 110.16, 3.494, 2.072], [30, 0.83, 70.2, 2.36, 1.4], [36, 0.368, 30.24, 1.226, 0.728], [40, 0.06, 3.6, 0.47, 0.28], [41, 0, 0, 0, 0]]);

test('growth macro minerals use gain in kilograms per day', function (array $parameters, array $expected) {
    $ration = correctedScientificRation(array_merge(['lait_objectif' => 0, 'mois_lactation' => 0], $parameters));
    foreach (['Na', 'K', 'Mgabs', 'Cl'] as $i => $mineral) {
        expect(Besoin::{'calculerBesoin'.$mineral}($ration))->toEqualWithDelta($expected[$i], 1e-8);
    }
})->with([
    [['categorie_animal' => 'bovin_croissance', 'poids_vif' => 400, 'gmq' => 900], [7.26, 43.44, 4.76, 10.1]],
    [['categorie_animal' => 'agneau_croissance', 'poids_vif' => 30, 'gmq' => 300], [0.72, 3.69, 0.54, 0.9]],
    [['categorie_animal' => 'chevrette_croissance', 'poids_vif' => 35, 'gmq' => 150], [0.705, 3.945, 0.445, 0.955]],
]);

test('small ruminant gestation minerals use days and litter size in the last third', function (string $category, int $boundary, int $kids, array $expected) {
    $ration = correctedScientificRation(['categorie_animal' => $category, 'poids_vif' => 60, 'lait_objectif' => 0, 'mois_gestation' => null, 'jours_gestation' => $boundary, 'nombre_jeunes' => $kids]);
    $before = clone $ration;
    $before->jours_gestation = $boundary - 1;
    foreach (['Na', 'K', 'Mgabs', 'Cl'] as $i => $mineral) {
        expect(Besoin::{'calculerBesoin'.$mineral}($ration) - Besoin::{'calculerBesoin'.$mineral}($before))->toEqualWithDelta($expected[$i], 1e-8);
    }
})->with([
    ['brebis_allaitante', 98, 1, [0.3, 0.2, 0.03, 0.4]], ['brebis_laitiere', 98, 2, [0.3, 0.3, 0.05, 0.4]],
    ['chevre_laitiere', 100, 2, [0.3, 0.3, 0.05, 0.4]], ['chevrette_croissance', 100, 1, [0.3, 0.2, 0.03, 0.4]],
]);

test('bovine and caprine milk macro minerals convert kilograms to litres', function (string $category) {
    $ration = correctedScientificRation(['categorie_animal' => $category, 'lait_objectif' => 10.32, 'parite' => 3]);
    expect(Besoin::calculerBesoinNa($ration))->toEqualWithDelta(0.023 * 650 + 4.5, 1e-8);
})->with(['vache_laitiere', 'chevre_laitiere']);

test('suckling ewe macro minerals use the same estimated milk as calcium and phosphorus', function () {
    $ration = correctedScientificRation(['categorie_animal' => 'brebis_allaitante', 'poids_vif' => 60, 'lait_objectif' => null, 'jours_lactation' => 21, 'gmq_portee' => 300, 'mfc' => null, 'mpc' => null]);
    $milk = 0.97088 / (0.686 * (0.0071 * 76 + 0.0043 * 55 + 0.2224));
    expect(Besoin::calculerBesoinNa($ration))->toEqualWithDelta(0.023 * 60 + 0.45 * $milk, 1e-8);
    expect(Besoin::calculerBesoinMgabs($ration))->toEqualWithDelta(0.014 * 60 + 0.18 * $milk, 1e-8);
});

test('nitrogen predictions are invariant to direct ingredients or a mixture in fresh matter', function () {
    $direct = correctedScientificRation([], 14);
    $hay = $direct->rationAliments->sole()->aliment;
    $grain = Aliment::factory()->systemique()->create(['type' => 'concentre', 'ms' => 90, 'ufl' => 1.15, 'pdi' => 130, 'eb' => 4800, 'mo' => 960, 'd_mo' => 85, 'mat' => 210]);
    $direct->rationAliments()->create(['aliment_id' => $grain->id, 'quantite' => 6, 'is_mb' => false, 'is_volonte' => false, 'ordre' => 2]);
    $direct->load('rationAliments.aliment');
    $mixed = correctedScientificRation([], 0);
    $mix = $mixed->melanges()->create(['nom' => 'Même ration en MB', 'quantite' => 14 / 0.3 + 6 / 0.9, 'is_mb' => true, 'is_volonte' => false, 'ordre' => 1]);
    foreach ([[$hay, 14 / 0.3], [$grain, 6 / 0.9]] as $i => [$feed, $quantity]) {
        $mix->melangeAliments()->create(['aliment_id' => $feed->id, 'quantite' => $quantity, 'is_mb' => true, 'ordre' => $i + 1]);
    }
    $mixed->load('melanges.melangeAliments.aliment');
    foreach ([fn ($r) => Apport::calculerMAmic_duo($r), fn ($r) => Impact::calculerNND($r), fn ($r) => Impact::calculerNU($r)] as $calculation) {
        expect($calculation($mixed))->toEqualWithDelta($calculation($direct), 1e-8);
    }
});

test('bovine drinking water uses forage protein concentration before the forage proportion', function () {
    $ration = correctedScientificRation([], 14);
    $grain = Aliment::factory()->systemique()->create(['type' => 'concentre', 'ms' => 85, 'ufl' => 1.15, 'pdi' => 130, 'eb' => null]);
    $ration->rationAliments()->create(['aliment_id' => $grain->id, 'quantite' => 6, 'is_mb' => false, 'is_volonte' => false, 'ordre' => 2]);
    $ration->load('rationAliments.aliment');
    $freshWeight = 14 / 0.3 + 6 / 0.85;
    $expected = -4.34 + 0.88 * 30 + 20 * (4.6 - $freshWeight / 20) + 0.0012 * (150 * 0.7) ** 2;
    expect(Impact::calculerEauBue($ration))->toEqualWithDelta($expected, 1e-8);
    expect(App\Services\Equations2007\Impact::calculerEauBue($ration))->toEqualWithDelta($expected, 1e-8);
});

test('suckler permitted milk uses the same costs as its milk requirements', function () {
    $ration = correctedScientificRation(['categorie_animal' => 'vache_allaitante', 'lait_objectif' => 8, 'mois_lactation' => 2]);
    $uf = Apport::calculerApportTotalUF($ration) - Besoin::calculerBesoinTotalUF($ration) + Besoin::calculerBesoinUF_PL($ration);
    $pdi = Apport::calculerApportTotalPDI($ration) - Besoin::calculerBesoinTotalPDI($ration) + Besoin::calculerBesoinPDI_PL($ration);
    expect(Impact::calculerLaitPermisParUF($ration))->toEqualWithDelta($uf / 0.44, 1e-8);
    expect(Impact::calculerLaitPermisParPDI($ration))->toEqualWithDelta($pdi / (34 / Apport::calculerEffPDI($ration)), 1e-8);
});

test('non pregnant cows have zero gestation in INRA 2007', function (string $category) {
    $ration = correctedScientificRation(['categorie_animal' => $category]);
    expect(Besoin2007::calculerBesoinUF_gest($ration))->toBe(0.0);
    expect(Besoin2007::calculerBesoinPDI_gest($ration))->toBe(0.0);
    $ration->mois_gestation = 8;
    expect(Besoin2007::calculerBesoinUF_gest($ration))->toBeGreaterThan(0);
    expect(Besoin2007::calculerBesoinPDI_gest($ration))->toBeGreaterThan(0);
})->with(['vache_laitiere', 'vache_allaitante']);

test('young ewe growth and replenishment match equations 20.15, 20.25 and 20.26', function () {
    $ration = correctedScientificRation(['categorie_animal' => 'brebis_laitiere', 'poids_vif' => 65, 'poids_adulte' => 75, 'gmq' => 100, 'jours_gestation' => 60, 'lait_objectif' => 0, 'variation_nec' => 1, 'duree_variation_nec_jours' => 60]);
    expect(Besoin::calculerBesoinUF_gain($ration))->toBe(0.28);
    expect(Besoin::calculerBesoinPDI_gain($ration) * Apport::calculerEffPDI($ration))->toEqualWithDelta(13, 1e-8);
    expect(Besoin::calculerBesoinUF_DRC($ration))->toEqualWithDelta(0.433 * 65 / 60, 1e-8);
    expect(Besoin::calculerBesoinPDI_DRC($ration))->toEqualWithDelta(0.433 * 65 / 60 * 33, 1e-8);
    $ration->variation_nec = -1;
    $ration->duree_variation_nec_jours = 42;
    expect(Besoin::calculerBesoinUF_DRC($ration))->toEqualWithDelta(-28 / 42, 1e-8);
});

test('goat body reserve variation uses empty weight and the protein relation 21.10', function (float $change) {
    $ration = correctedScientificRation(['categorie_animal' => 'chevre_laitiere', 'ecart_variation_reserve' => $change]);
    expect(Besoin::calculerBesoinUF_DRC($ration))->toEqualWithDelta(2.22 * $change, 1e-8);
    expect(Besoin::calculerBesoinPDI_DRC($ration))->toEqualWithDelta(33 * 2.22 * $change, 1e-8);
})->with([-0.1, 0.1]);

test('a pregnant replacement doe retains growth and adds fetal requirements', function () {
    $ration = correctedScientificRation(['categorie_animal' => 'chevrette_croissance', 'poids_vif' => 40, 'poids_adulte' => 76.6, 'age_jours' => 300, 'race' => 'alpine', 'gmq' => 85, 'lait_objectif' => 0, 'jours_gestation' => 120, 'nombre_jeunes' => 2]);
    $nonPregnant = clone $ration;
    $nonPregnant->jours_gestation = 0;
    expect(Besoin::calculerBesoinUF_gest($ration))->toEqualWithDelta(3 * exp(0.034 * 120) / 0.14 * 0.65 / 1760, 1e-8);
    expect(Besoin::calculerBesoinPDI_gest($ration) * Apport::calculerEffPDI($ration))->toEqualWithDelta(0.35 * exp(0.03383 * 120), 1e-8);
    expect(Besoin::calculerBesoinCaabs($ration) - Besoin::calculerBesoinCaabs($nonPregnant))->toEqualWithDelta(2, 1e-8);
    expect(Besoin::calculerBesoinPabs($ration) - Besoin::calculerBesoinPabs($nonPregnant))->toEqualWithDelta(1.2, 1e-8);
    expect(Besoin::calculerBesoinUF_gain($ration))->toBeGreaterThan(0);
});

test('new physiological parameters persist through the form, results and duplication', function (string $category, array $parameters) {
    $user = User::factory()->create();
    $ration = correctedScientificRation(['categorie_animal' => $category], 0);
    $plan = $ration->planRationnement;
    $plan->update(['user_id' => $user->id]);
    $this->actingAs($user)->put(route('plans.rations.description.update', [$plan, $ration]), array_merge([
        'nom' => 'Validation physiologique', 'categorie_animal' => $category,
        'poids_vif' => 40, 'race' => 'alpine', 'parite' => 1, 'jours_lactation' => 0,
        'mois_lactation' => 0, 'lait_objectif' => 0,
    ], $parameters))->assertSessionHasNoErrors()->assertRedirect();
    $ration->refresh();
    foreach ($parameters as $field => $expected) {
        expect((float) $ration->$field)->toEqualWithDelta((float) $expected, 1e-8);
    }
    $this->get(route('plans.rations.resultats', [$plan, $ration]))->assertOk();
    $this->post(route('plans.rations.duplicate', [$plan, $ration]))->assertRedirect();
    $copy = $plan->rations()->latest('id')->first();
    foreach ($parameters as $field => $expected) {
        expect((float) $copy->$field)->toEqualWithDelta((float) $expected, 1e-8);
    }
})->with([
    ['vache_laitiere', ['age_mois' => 24, 'pourcentage_primipare' => 100, 'mois_lactation' => 3, 'lait_objectif' => 25]],
    ['brebis_laitiere', ['gmq' => 100, 'poids_adulte' => 75, 'jours_gestation' => 60, 'nombre_jeunes' => 2, 'poids_portee' => 8, 'variation_nec' => 0.5, 'duree_variation_nec_jours' => 60]],
    ['chevrette_croissance', ['gmq' => 85, 'age_jours' => 300, 'sexe' => 'femelle', 'poids_adulte' => 76.6, 'jours_gestation' => 120, 'nombre_jeunes' => 2, 'poids_portee' => 8]],
]);

test('reserve duration and young ewe growth outside the published domain are rejected', function (array $parameters, string $field) {
    $user = User::factory()->create();
    $ration = correctedScientificRation(['categorie_animal' => 'brebis_laitiere'], 0);
    $plan = $ration->planRationnement;
    $plan->update(['user_id' => $user->id]);
    $this->actingAs($user)->put(route('plans.rations.description.update', [$plan, $ration]), array_merge([
        'nom' => 'Brebis', 'categorie_animal' => 'brebis_laitiere', 'poids_vif' => 65, 'race' => 'lacaune',
        'parite' => 1, 'jours_lactation' => 0, 'jours_gestation' => 0,
    ], $parameters))->assertSessionHasErrors($field);
})->with([
    [['variation_nec' => 1], 'duree_variation_nec_jours'],
    [['gmq' => 100, 'poids_adulte' => 75], 'gmq'],
    [['variation_nec' => -1, 'duree_variation_nec_jours' => 42], 'variation_nec'],
]);

test('high quality forage permits a negative dairy energy pivot and equation 17.38 converges', function () {
    $ration = correctedScientificRation(['lait_objectif' => 5, 'lait_potentiel305j' => null], 14);
    $hay = $ration->rationAliments->sole()->aliment;
    $hay->update(['ufl' => 1.0, 'uel' => 0.9]);
    $grain = Aliment::factory()->systemique()->create(['type' => 'concentre', 'ms' => 90, 'ufl' => 1.15, 'pdi' => 130, 'eb' => null]);
    $ration->rationAliments()->create(['aliment_id' => $grain->id, 'quantite' => 2, 'is_mb' => false, 'is_volonte' => false, 'ordre' => 2]);
    $ration->load('rationAliments.aliment');
    $pivot = Apport::calculerRPCO($ration);
    expect($pivot)->toBeLessThan(0);
    $ci = Besoin::calculerCapaciteIngestion($ration);
    $energy = Besoin::calculerBesoinTotalUF($ration);
    $substitution = Apport::calculerTSg(0.9, Apport::calculerbVEc($ration), 1, 1.15,
        Apport::calculerApportTotalPDI($ration) / 16, Apport::calculerApportTotalUF($ration) / 16, $pivot, $pivot);
    $residual = $pivot * ($energy * ($substitution - 1) - $ci / 0.9 * (1.15 - 1)) - ($ci / 0.9 - $energy);
    expect($residual)->toEqualWithDelta(0, 1e-7);
});

test('primiparous herd growth is weighted even when its size is unspecified', function (?int $size) {
    $ration = correctedScientificRation(['effectif' => $size, 'parite' => null, 'pourcentage_primipare' => 30, 'age_mois' => 24]);
    expect(Besoin::calculerBesoinUF_gain($ration))->toEqualWithDelta(1.292 * 0.3, 1e-8);
    expect(Besoin::calculerBesoinPDI_gain($ration) * Apport::calculerEffPDI($ration))->toEqualWithDelta(110.16 * 0.3, 1e-8);
    $adult = clone $ration;
    $adult->age_mois = 60;
    expect(Besoin::calculerBesoinCaabs($ration) - Besoin::calculerBesoinCaabs($adult))->toEqualWithDelta(3.494 * 0.3, 1e-8);
    expect(Besoin::calculerBesoinPabs($ration) - Besoin::calculerBesoinPabs($adult))->toEqualWithDelta(2.072 * 0.3, 1e-8);
})->with([null, 100]);

test('goat reserve mobilisation does not reduce the calcium and phosphorus of its gravid uterus', function () {
    $ration = correctedScientificRation(['categorie_animal' => 'chevre_laitiere', 'poids_vif' => 70, 'lait_objectif' => 0, 'jours_gestation' => 140, 'nombre_jeunes' => 2]);
    $ca = Besoin::calculerBesoinCaabs($ration);
    $p = Besoin::calculerBesoinPabs($ration);
    $energy = Besoin::calculerBesoinTotalUF($ration);
    $ration->ecart_variation_reserve = -0.1;
    expect(Besoin::calculerBesoinCaabs($ration))->toEqualWithDelta($ca, 1e-8);
    expect(Besoin::calculerBesoinPabs($ration))->toEqualWithDelta($p, 1e-8);
    expect(Besoin::calculerBesoinTotalUF($ration))->toEqualWithDelta($energy - 0.222, 1e-8);
});
