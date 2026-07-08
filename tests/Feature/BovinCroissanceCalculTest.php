<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Services\Equations2018\Besoin as Besoin2018;
use App\Services\RationCalculator;

/**
 * Cas de référence bovins croissance/engraissement tirés du chapitre 19 (INRA 2018).
 */
function bovinRation(array $overrides, float $forageMs = 6.0): Ration
{
    $plan = PlanRationnement::factory()->create(['inra' => '2018']);
    $ration = Ration::factory()->create(array_merge([
        'plan_rationnement_id' => $plan->id,
        'race' => 'charolaise',
    ], $overrides));

    $forage = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'ms' => 100,
        'mo' => 900,
        'mat' => 140,
        'd_mo' => 72,
        'ufv' => 0.90,
    ]);
    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $forage->id,
        'quantite' => $forageMs,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 1,
    ]);

    return $ration->load(['planRationnement', 'rationAliments.aliment', 'melanges.melangeAliments.aliment']);
}

test('finishing young bull energy reproduces book example Box 19.1 (Charolais 450 kg, 1400 g/d → 7.7 UFV)', function () {
    $ration = bovinRation([
        'categorie_animal' => 'bovin_engraissement',
        'race' => 'charolaise',
        'poids_vif' => 450,
        'gmq' => 1400,
    ]);

    // Box 19.1 : entretien 4,8 UFV + gain 2,9 UFV = 7,7 UFV/j.
    expect(Besoin2018::calculerBesoinUF_NP($ration))->toEqualWithDelta(4.8, 0.15);
    expect(Besoin2018::calculerBesoinUF_gain($ration))->toEqualWithDelta(2.9, 0.15);
    expect(Besoin2018::calculerBesoinTotalUF($ration))->toEqualWithDelta(7.7, 0.2);
});

test('finishing bull intake capacity follows the allometric equation 19.18', function () {
    $ration = bovinRation([
        'categorie_animal' => 'bovin_engraissement',
        'race' => 'charolaise',
        'poids_vif' => 450,
        'gmq' => 1400,
    ]);

    // IC = Itype × BW^c, Charolais réf. Itype = 0,284, c = 0,54 (Table 19.3).
    expect(Besoin2018::calculerCapaciteIngestion($ration))->toEqualWithDelta(0.284 * 450 ** 0.54, 0.001);
});

test('finishing bull mineral requirements follow equations 19.14 and 19.15', function () {
    $ration = bovinRation([
        'categorie_animal' => 'bovin_engraissement',
        'race' => 'charolaise',
        'poids_vif' => 450,
        'gmq' => 1400,
    ]);

    $bwAdult = 300 * exp(1.1028); // BWinitial × exp(d1)
    $adg = 1.4;
    $expectedCa = 0.015 * 450 + 9.83 * $bwAdult ** 0.22 * 450 ** -0.22 * $adg;
    $expectedP = 0.025 * 450 + (1.2 + 4.66 * $bwAdult ** 0.22 * 450 ** -0.22) * $adg;

    expect(Besoin2018::calculerBesoinCaabs($ration))->toEqualWithDelta($expectedCa, 0.01);
    expect(Besoin2018::calculerBesoinPabs($ration))->toEqualWithDelta($expectedP, 0.01);
});

test('growing heifer uses the UFL system and produces positive requirements', function () {
    $ration = bovinRation([
        'categorie_animal' => 'bovin_croissance',
        'race' => 'laitiere',
        'poids_vif' => 350,
        'gmq' => 800,
    ]);

    expect(Besoin2018::calculerBesoinTotalUF($ration))->toBeGreaterThan(0.0);
    expect(Besoin2018::calculerCapaciteIngestion($ration))->toBeGreaterThan(0.0);
    // CI croissance (Table 19.3, génisses laitières) : Itype = 0,03915, c = 0,90.
    expect(Besoin2018::calculerCapaciteIngestion($ration))->toEqualWithDelta(0.03915 * 350 ** 0.90, 0.001);
});

test('finishing bull ration exposes UFV energy and no bovine milk impacts', function () {
    $resultats = RationCalculator::calculer(bovinRation([
        'categorie_animal' => 'bovin_engraissement',
        'race' => 'charolaise',
        'poids_vif' => 450,
        'gmq' => 1400,
    ]));

    expect($resultats['besoins']['uf_total'])->toBeGreaterThan(0.0);
    expect($resultats['besoins']['ci'])->toBeGreaterThan(0.0);
    expect($resultats['impacts'])->not->toHaveKey('lait_par_ufl');
    expect($resultats['impacts'])->not->toHaveKey('production_lait_attendue');
    // Énergie exprimée en UFV (colonne ufv = 0,90 × 6 kg MS).
    expect($resultats['apports']['ufl'])->toEqualWithDelta(0.90 * 6.0, 0.01);
});
