<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Services\Equations2018\Apport as Apport2018;
use App\Services\Equations2018\Besoin as Besoin2018;
use App\Services\Equations2018\BovinCroissanceBesoin;
use App\Services\Equations2018\CalculValeur;
use App\Services\RationCalculator;
use App\Services\Reference\BovinCroissanceReference;

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

test('growing cattle maintenance uses q prime with ADG expressed in kg per day', function (int $adg, float $expectedMaintenance) {
    $ration = bovinRation([
        'categorie_animal' => 'bovin_croissance', 'reference_bovine' => 10,
        'poids_vif' => 400, 'gmq' => $adg,
    ]);

    // INRA 2018, ch. 19.3.1, étapes 1 et 4 : q′ = 0,62 − 0,262 × exp(−3,175 × GMQ kg/j).
    expect(Besoin2018::calculerBesoinUF_NP($ration))->toEqualWithDelta($expectedMaintenance, 0.00001);
})->with([
    [0, 4.2393005637], [400, 4.2295598589], [800, 4.2270853365], [1000, 4.2266423209],
]);

test('bovine mineral requirements use the specified adult weight', function () {
    $ration = bovinRation([
        'categorie_animal' => 'bovin_croissance', 'reference_bovine' => 13,
        'poids_vif' => 350, 'poids_adulte' => 650, 'gmq' => 800,
    ]);

    expect(Besoin2018::calculerBesoinCaabs($ration))
        ->toEqualWithDelta(0.015 * 350 + 9.83 * (650 / 350) ** 0.22 * 0.8, 1e-9);
    expect(Besoin2018::calculerBesoinPabs($ration))
        ->toEqualWithDelta(0.025 * 350 + (1.2 + 4.66 * (650 / 350) ** 0.22) * 0.8, 1e-9);
});

test('bovine energy and intake reproduce the INRA tables 19.5, 19.6 and 19.7', function (int $reference, int $weight, int $gmq, float $energy, float $intake) {
    $ration = bovinRation([
        'categorie_animal' => $reference <= 9 ? 'bovin_engraissement' : 'bovin_croissance',
        'reference_bovine' => $reference, 'poids_vif' => $weight, 'gmq' => $gmq,
    ]);
    // Les recommandations tabulées sont des valeurs approchées ; les équations utilisent leurs coefficients complets.
    expect(Besoin2018::calculerBesoinTotalUF($ration))->toEqualWithDelta($energy, 0.15);
    expect(Besoin2018::calculerCapaciteIngestion($ration))->toEqualWithDelta($intake, 0.08);
})->with([
    // Tableau 19.5 : génisses du troupeau allaitant.
    [12, 300, 600, 4.44, 5.9], [12, 400, 800, 5.88, 7.6], [12, 600, 1000, 8.67, 10.9],
    // Tableau 19.6 : génisses du troupeau laitier.
    [13, 150, 800, 3.4, 3.6], [13, 350, 800, 5.7, 7.6], [13, 600, 800, 8.8, 12.4],
    // Tableau 19.7 : taurillons Charolais.
    [1, 300, 1400, 6.0, 6.2], [1, 450, 1400, 7.7, 7.7], [1, 500, 1800, 9.5, 8.1],
]);

test('explicit bovine references preserve the intake corrections from table 19.3', function (int $reference, string $race, float $coefficient, float $exponent) {
    $animal = BovinCroissanceReference::resolve($race, $reference <= 9, $reference);
    expect($animal['i_type'])->toBe($coefficient);
    expect($animal['c'])->toBe($exponent);
})->with([
    [10, 'limousine', 0.03115, 0.9], [12, 'limousine', 0.03115, 0.9],
    [10, 'charolaise', 0.03459, 0.9], [4, 'croiselaitiere', 0.20, 0.60],
]);

test('bovine protein needs match equations 19.9 to 19.13 and substitution matches 19.20', function (string $category, int $reference, int $gmq) {
    $ration = bovinRation([
        'categorie_animal' => $category, 'reference_bovine' => $reference,
        'poids_vif' => 450, 'gmq' => $gmq,
    ]);
    $ration->rationAliments->first()->aliment->forceFill(['pdi' => 90, 'ueb' => 1.1]);
    $concentrate = Aliment::factory()->systemique()->create([
        'type' => 'concentre', 'ms' => 100, 'ufl' => 1.1, 'ufv' => 1.0, 'pdi' => 100, 'eb' => null,
        'mo' => 960, 'd_mo' => 80, 'mat' => 150,
    ]);
    $ration->rationAliments->push(new RationAliment([
        'quantite' => 2, 'is_mb' => false, 'is_volonte' => false,
    ])->setRelation('aliment', $concentrate));

    $eff = Apport2018::calculerEffPDI($ration);
    $mond = Apport2018::calculerApportMOND($ration);
    $nonproductive = 8 * 0.5 * (5.7 + 0.074 * $mond) / $eff + 0.312 * 450 + 0.2 * 450 ** 0.6 / $eff;
    expect(Besoin2018::calculerBesoinPDI_NP($ration))->toEqualWithDelta($nonproductive, 1e-9);
    $gain = BovinCroissanceBesoin::depotProteiqueNet($ration);
    expect(Besoin2018::calculerBesoinTotalPDI($ration))->toEqualWithDelta($nonproductive + $gain / $eff, 1e-9);

    $a = 1.1 * (1 - 1.26 * (1.1 - 0.85) ** 0.84);
    $b = 1.1 ** 2 / (2.04 * (1 - $a * 1.1));
    $k = (1 - $a) * exp($b / 0.7);
    $concentrateFill = 0.86 * (1 - $k * exp(-$b / (1 - 0.25)));
    expect(Apport2018::calculerApportTotalUE($ration))->toEqualWithDelta(6 * 1.1 + 2 * $concentrateFill, 1e-9);
})->with([
    ['bovin_croissance', 10, 800], ['bovin_engraissement', 1, 1400],
]);

test('bovine rations recalculate energy from analytical food values in the correct system', function (string $category, int $reference, int $gmq) {
    $ration = bovinRation([
        'categorie_animal' => $category, 'reference_bovine' => $reference,
        'poids_vif' => 450, 'gmq' => $gmq,
    ]);
    $forage = $ration->rationAliments->first()->aliment;
    $forage->update([
        'ufl' => 0.01, 'ufv' => 0.01, 'pdi' => 90, 'ueb' => 1.1,
        'eb' => 4400, 'mo' => 900, 'd_mo' => 65, 'mat' => 120,
        'dt6_n' => 65, 'dr_n' => 70, 'niref' => 2, 'bpr' => 0,
        'famille_botanique' => 'graminee', 'procede_technologique' => 'foin',
    ]);
    $ration->refresh()->load(['planRationnement', 'rationAliments.aliment', 'melanges.melangeAliments.aliment']);
    $calculation = new CalculValeur($ration, $forage);
    $expectedPerKg = $reference <= 9 ? $calculation->calculerUFVAliment() : $calculation->calculerUFLAliment();
    $result = RationCalculator::calculer($ration);
    expect($expectedPerKg)->toBeGreaterThan(0.1);
    expect($result['apports']['uf'])->toEqualWithDelta(6 * $expectedPerKg, 0.005);
    expect($result['apports']['pdi'])->toBeGreaterThan(0.0);
    expect($result['bilans']['uf'])->toEqualWithDelta($result['apports']['uf'] - $result['besoins']['uf_total'], 0.011);
    array_walk_recursive($result, function ($value): void {
        if (is_numeric($value)) {
            expect(is_finite((float) $value))->toBeTrue();
        }
    });
})->with([
    ['bovin_croissance', 10, 800], ['bovin_engraissement', 1, 1400],
]);
