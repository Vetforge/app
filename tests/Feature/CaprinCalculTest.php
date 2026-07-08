<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Services\Equations2018\Besoin as Besoin2018;
use App\Services\RationCalculator;

/**
 * Cas de référence caprins tirés du chapitre 21 (INRA 2018).
 */
function chevreRation(array $overrides = []): Ration
{
    $plan = PlanRationnement::factory()->create(['inra' => '2018']);
    $ration = Ration::factory()->create(array_merge([
        'plan_rationnement_id' => $plan->id,
        'categorie_animal' => 'chevre_laitiere',
        'poids_vif' => 70,
        'lait_objectif' => 3.5,
        'mfc' => 35,
        'mpc' => 31,
        'jours_lactation' => 120,
        'jours_gestation' => 0,
        'nombre_jeunes' => 2,
    ], $overrides));

    // Fourrage unique : 2,6 kg MS (ms = 100 %), MAT 150 g/kg pour l'indice CI protéines.
    $forage = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'ms' => 100,
        'mo' => 900,
        'mat' => 150,
        'd_mo' => 70,
    ]);
    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $forage->id,
        'quantite' => 2.6,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 1,
    ]);

    return $ration->load(['planRationnement', 'rationAliments.aliment', 'melanges.melangeAliments.aliment']);
}

test('dairy goat mineral requirements match the book example (70 kg, 3.5 kg milk, 2.6 kg DM)', function () {
    $ration = chevreRation();

    // Livre ch. 21 : Caabs ≈ 6,7 g/j et Pabs ≈ 6,0 g/j (Éq. 21.20-21.21).
    expect(Besoin2018::calculerBesoinCaabs($ration))->toEqualWithDelta(6.68, 0.05);
    expect(Besoin2018::calculerBesoinPabs($ration))->toEqualWithDelta(6.01, 0.05);
});

test('dairy goat maintenance and milk energy follow equations 21.6 and 21.7a', function () {
    $ration = chevreRation();

    // Entretien : 0,0406 × 70^0,75 (Éq. 21.6).
    expect(Besoin2018::calculerBesoinUF_NP($ration))->toEqualWithDelta(0.0406 * 70 ** 0.75, 0.001);
    // Lait à MFC = 35 : 3,5 × 0,389 (Éq. 21.7a).
    expect(Besoin2018::calculerBesoinUF_PL($ration))->toEqualWithDelta(3.5 * 0.389, 0.001);
});

test('milk fat content raises the dairy goat energy requirement (equation 21.7a)', function () {
    // TP non renseigné → équation 21.7a (fonction du seul MFC).
    $ration = chevreRation(['mfc' => 45, 'mpc' => null]);

    // 3,5 × [0,389 + 0,0056 × (45 - 35)].
    expect(Besoin2018::calculerBesoinUF_PL($ration))->toEqualWithDelta(3.5 * (0.389 + 0.0056 * 10), 0.001);
});

test('milk protein content refines the dairy goat energy requirement (equation 21.7b)', function () {
    $ration = chevreRation(['mfc' => 45, 'mpc' => 34]);

    // 3,5 × [0,389 + 0,0052 × (45 - 35) + 0,0029 × (34 - 31)].
    expect(Besoin2018::calculerBesoinUF_PL($ration))->toEqualWithDelta(3.5 * (0.389 + 0.0052 * 10 + 0.0029 * 3), 0.001);
});

test('growing goat energy and intake capacity match table 21.7 (Alpine, ~5 months)', function () {
    $ration = chevreRation([
        'categorie_animal' => 'chevrette_croissance',
        'poids_vif' => 27.7,
        'lait_objectif' => 0,
        'gmq' => 127,
        'jours_lactation' => 0,
    ]);

    // Table 21.7 : ≈ 0,68 UFL/j à 27,7 kg pour 127 g/j de gain.
    expect(Besoin2018::calculerBesoinTotalUF($ration))->toEqualWithDelta(0.68, 0.03);
    // Capacité d'ingestion chevrette (Éq. 21.50) : 0,080 × BW^0,75.
    expect(Besoin2018::calculerCapaciteIngestion($ration))->toEqualWithDelta(0.080 * 27.7 ** 0.75, 0.001);
});

test('dairy goat ration produces species-appropriate output without bovine milk impacts', function () {
    $resultats = RationCalculator::calculer(chevreRation());

    expect($resultats['inra'])->toBe('2018');
    // Bilans UFL/UEL/PDI présents et cohérents.
    expect($resultats['bilans'])->toHaveKeys(['ufl', 'ue', 'pdi', 'caabs', 'pabs']);
    // Les impacts « lait permis » bovins ne s'appliquent pas aux caprins.
    expect($resultats['impacts'])->not->toHaveKey('lait_par_ufl');
    expect($resultats['impacts'])->not->toHaveKey('production_lait_attendue');
    // Besoins caprins non nuls.
    expect($resultats['besoins']['uf_total'])->toBeGreaterThan(0.0);
    expect($resultats['besoins']['ci'])->toBeGreaterThan(0.0);
});
