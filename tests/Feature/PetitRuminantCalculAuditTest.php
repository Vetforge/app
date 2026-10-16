<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Services\Equations2018\Apport;
use App\Services\Equations2018\Besoin;
use App\Services\RationCalculator;

function petitRuminantAuditRation(array $parameters, float $dryMatter = 1.5): Ration
{
    $plan = PlanRationnement::factory()->create(['inra' => '2018']);
    $ration = Ration::factory()->create(array_merge([
        'plan_rationnement_id' => $plan->id, 'race' => 'autre', 'poids_vif' => 60,
        'jours_lactation' => 0, 'jours_gestation' => 0, 'lait_objectif' => null,
        'lait_potentiel' => null, 'nec' => 3, 'activite' => 'stabulation', 'temperature_ambiante' => 18,
    ], $parameters));
    if ($dryMatter > 0) {
        $forage = Aliment::factory()->systemique()->create([
            'type' => 'fourrage', 'ms' => 85, 'ufl' => 0.8, 'ufv' => 0.9, 'pdi' => 100,
            'uem' => 1.2, 'uel' => 1.1, 'eb' => null, 'mo' => 900, 'd_mo' => 100 * 2 / 3, 'mat' => 150,
        ]);
        $ration->rationAliments()->create(['aliment_id' => $forage->id, 'quantite' => $dryMatter, 'is_mb' => false, 'is_volonte' => false, 'ordre' => 1]);
    }

    return $ration->load(['planRationnement', 'rationAliments.aliment', 'melanges.melangeAliments.aliment']);
}

test('sheep intake applies equation 20.52 only below thermoneutrality', function (float $temperature, float $factor) {
    $ration = petitRuminantAuditRation(['categorie_animal' => 'brebis_allaitante', 'temperature_ambiante' => $temperature]);
    expect(Besoin::calculerCapaciteIngestion($ration))->toEqualWithDelta(0.07 * 60 ** 0.75 * $factor, 1e-8);
})->with([[10.0, 1.162], [15.0, 1.0], [18.0, 1.0], [25.0, 1.0]]);

test('suckling ewe substitution uses the concentrate exponent of equation 20.45', function () {
    $ration = petitRuminantAuditRation(['categorie_animal' => 'brebis_allaitante', 'jours_lactation' => 14, 'gmq_portee' => 350]);
    $grain = Aliment::factory()->systemique()->create(['type' => 'concentre', 'ms' => 90, 'ufl' => 1.1, 'ufv' => 1.0, 'pdi' => 110, 'eb' => null]);
    $ration->rationAliments()->create(['aliment_id' => $grain->id, 'quantite' => 0.4, 'is_mb' => false, 'is_volonte' => false, 'ordre' => 2]);
    $ration->load('rationAliments.aliment');
    $correction = (1.5 - 2.573) * (0.3572 * 0.4 ** -0.986) + 0.202;
    $substitution = 0.8 * (-1.6 * 1.2 + 2.9) + $correction;
    expect(Apport::calculerSg($ration))->toEqualWithDelta($substitution, 1e-8);
    expect(Apport::calculerApportTotalUE($ration))->toEqualWithDelta(1.5 * 1.2 + 0.4 * $substitution * 1.2, 1e-8);
});

test('suckling ewe minerals include the milk estimated from litter gain when milk is unspecified', function () {
    $ration = petitRuminantAuditRation(['categorie_animal' => 'brebis_allaitante', 'jours_lactation' => 21, 'gmq_portee' => 300, 'nombre_jeunes' => 2]);
    $milk = 0.97088 / (0.686 * (0.0071 * 76 + 0.0043 * 55 + 0.2224));
    expect(Besoin::calculerBesoinCaabs($ration))->toEqualWithDelta(0.67 * 1.5 + 0.01 * 60 + 1.9 * $milk, 1e-8);
    expect(Besoin::calculerBesoinPabs($ration))->toEqualWithDelta(0.905 * 1.5 + 0.002 * 60 + 0.3 + 1.5 * $milk, 1e-8);
});

test('late ewe gestation follows equations 20.12, 20.22, 20.31 and 20.35', function () {
    $ration = petitRuminantAuditRation(['categorie_animal' => 'brebis_allaitante', 'poids_vif' => 65, 'nec' => 2.5, 'jours_gestation' => 126, 'nombre_jeunes' => 2, 'poids_portee' => 8]);
    expect(Besoin::calculerBesoinUF_gest($ration))->toEqualWithDelta(0.4151, 1e-8);
    expect(Besoin::calculerBesoinPDI_gest($ration))->toEqualWithDelta(77.45, 1e-8);
    expect(Besoin::calculerCapaciteIngestion($ration))->toEqualWithDelta(0.075 * 65 ** 0.75 + 0.0503 * 8 - 0.152 * 2 - 0.272, 1e-8);
    expect(Besoin::calculerBesoinCaabs($ration))->toEqualWithDelta(0.015 * 65 + (0.0093 * 9 - 0.127 * 3 + 0.571) * 8 + 0.263, 1e-8);
    expect(Besoin::calculerBesoinPabs($ration))->toEqualWithDelta(0.905 * 1.5 + 0.002 * 65 + 0.3 + (-0.03 * 3 + 0.24) * 8 - 0.04 * 3 + 0.33, 1e-8);
});

test('lamb energy and metabolic intake reproduce table 20.3', function (int $weight, int $gain, float $energy, float $intake) {
    $ration = petitRuminantAuditRation(['categorie_animal' => 'agneau_croissance', 'poids_vif' => $weight, 'gmq' => $gain, 'poids_adulte' => 70]);
    expect(Besoin::calculerBesoinTotalUF($ration))->toEqualWithDelta($energy, 0.006);
    expect(Besoin::calculerCapaciteIngestion($ration))->toEqualWithDelta($intake, 0.006);
    expect(Apport::calculerApportTotalUE($ration))->toBe(1.5);
})->with([[20, 100, 0.57, 0.625], [20, 300, 0.97, 1.021], [30, 250, 1.05, 1.135], [40, 400, 1.54, 1.646]]);

test('dairy goat empty composition has a positive intake capacity', function () {
    $ration = petitRuminantAuditRation(['categorie_animal' => 'chevre_laitiere', 'lait_objectif' => 3.5, 'jours_lactation' => 60], 0);
    $result = RationCalculator::calculer($ration);
    expect($result['besoins']['ci'])->toEqualWithDelta(2.14, 0.005);
});

test('dairy goat intake keeps the fifth week lactation correction', function (int $day, int $week) {
    $ration = petitRuminantAuditRation(['categorie_animal' => 'chevre_laitiere', 'jours_lactation' => $day, 'lait_objectif' => 3.5]);
    $index = $week <= 5 ? 0.5 + 0.5 * (1 - exp(-0.6 * $week)) : 1.0;
    expect(Besoin::calculerCapaciteIngestion($ration))->toEqualWithDelta(2.14 * $index * 1.014, 1e-8);
})->with([[1, 1], [7, 1], [8, 2], [28, 4], [29, 5], [35, 5], [36, 6]]);

test('goat gestation requirements follow equations 21.26 to 21.32', function (int $kids, float $energyConstant, float $proteinConstant) {
    $ration = petitRuminantAuditRation(['categorie_animal' => 'chevre_laitiere', 'jours_gestation' => 140, 'nombre_jeunes' => $kids]);
    $efficiency = Apport::calculerEffPDI($ration);
    expect(Besoin::calculerBesoinUF_gest($ration))->toEqualWithDelta($energyConstant * exp(0.034 * 140) / 0.14 * 0.65 / 1760, 1e-8);
    expect(Besoin::calculerBesoinPDI_gest($ration))->toEqualWithDelta($proteinConstant * exp(0.03383 * 140) / $efficiency, 1e-8);
    $totalEnergy = Besoin::calculerBesoinTotalUF($ration);
    expect(Besoin::calculerBesoinCaabs($ration))->toEqualWithDelta(2.52 * $totalEnergy + ($kids >= 2 ? 1 : 0), 1e-8);
    expect(Besoin::calculerBesoinPabs($ration))->toEqualWithDelta(2.22 * $totalEnergy + ($kids >= 2 ? 0.4 : 0), 1e-8);
})->with([[1, 1.8, 0.21], [2, 3.0, 0.35], [3, 4.1, 0.48]]);

test('growing goat energy and protein gain follow the requested growth', function (string $breed) {
    $ration = petitRuminantAuditRation(['categorie_animal' => 'chevrette_croissance', 'race' => $breed, 'age_jours' => 152, 'poids_vif' => 27.7, 'poids_adulte' => 70, 'gmq' => 127]);
    $energy = Besoin::calculerBesoinUF_gain($ration);
    $protein = Besoin::calculerBesoinPDI_gain($ration);
    $ration->gmq = 254;
    expect(Besoin::calculerBesoinUF_gain($ration))->toEqualWithDelta(2 * $energy, 1e-8);
    expect(Besoin::calculerBesoinPDI_gain($ration))->toEqualWithDelta(2 * $protein, 1e-8);
    $ration->gmq = 0;
    expect(Besoin::calculerBesoinUF_gain($ration))->toBe(0.0);
    expect(Besoin::calculerBesoinPDI_gain($ration))->toBe(0.0);
})->with(['alpine', 'saanen', 'autre']);

test('growing goat reference needs reproduce table 21.7 before gestation', function (string $breed, int $age, float $weight, int $gain, float $energy, float $protein) {
    $ration = petitRuminantAuditRation(['categorie_animal' => 'chevrette_croissance', 'race' => $breed, 'age_jours' => $age, 'poids_vif' => $weight, 'poids_adulte' => $breed === 'saanen' ? 94.6 : 76.6, 'gmq' => $gain], 0.065 * $weight ** 0.75);
    expect(Besoin::calculerBesoinTotalUF($ration))->toEqualWithDelta($energy, 0.025);
    expect(Besoin::calculerBesoinTotalPDI($ration))->toEqualWithDelta($protein, 2.0);
})->with([
    ['alpine', 30, 9.9, 173, 0.49, 70], ['alpine', 152, 27.7, 127, 0.68, 73], ['alpine', 243, 37.9, 100, 0.79, 74],
    ['saanen', 30, 9.4, 181, 0.50, 72], ['saanen', 152, 28.6, 141, 0.72, 78], ['saanen', 243, 40.1, 116, 0.85, 81],
]);

test('young dairy goats retain their growth needs during the first two reproductive cycles', function (string $breed, int $parity, int $lactation, int $gestation, float $energy, float $pdiReference) {
    $ration = petitRuminantAuditRation(['categorie_animal' => 'chevre_laitiere', 'race' => $breed, 'parite' => $parity, 'jours_lactation' => $lactation, 'jours_gestation' => $gestation]);
    expect(Besoin::calculerBesoinUF_gain($ration))->toEqualWithDelta($energy, 1e-8);
    expect(Besoin::calculerBesoinPDI_gain($ration))->toEqualWithDelta($pdiReference * 0.5 / Apport::calculerEffPDI($ration), 1e-8);
})->with([
    ['alpine', 1, 60, 0, 0.09, 15.4], ['alpine', 2, 60, 0, 0.04, 5.7],
    ['saanen', 1, 60, 0, 0.12, 19.8], ['saanen', 2, 60, 0, 0.06, 8.6],
    ['alpine', 0, 0, 140, 0.14, 21.9], ['saanen', 1, 0, 140, 0.08, 11.2],
    ['alpine', 3, 60, 0, 0.0, 0.0], ['alpine', 2, 0, 0, 0.0, 0.0],
]);
