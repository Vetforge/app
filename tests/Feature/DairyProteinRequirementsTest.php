<?php

declare(strict_types=1);

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Services\Equations2018\Apport;
use App\Services\Equations2018\Besoin;
use App\Services\RationCalculator;

/**
 * Cas analytique INRA 2018, chap. 17, équations 17.14–17.20 (édition française p. 281).
 * PV=650 kg, MSI=20 kg/j, MOND=300 g/kg MS, PDI=100 g/kg MS et EffPDI=0,67.
 * Les attentes numériques sont évaluées indépendamment depuis ces entrées et les équations publiées.
 *
 * @param  array<string, mixed>  $overrides
 */
function dairyProteinAuditRation(array $overrides = []): Ration
{
    $ration = new Ration(array_merge([
        'categorie_animal' => 'vache_laitiere', 'race' => 'prim_holstein', 'poids_vif' => 650,
        'effectif' => 1, 'parite' => 2, 'pourcentage_primipare' => 0, 'age_mois' => 50,
        'lait_objectif' => 40, 'mois_lactation' => 25 / 4.348, 'mois_gestation' => 0,
        'poids_veau_naissance' => 45, 'tb_annuel' => 40, 'tp_annuel' => 32,
        'nec' => 2.5, 'nec_velage' => null, 'ecart_variation_reserve' => 0,
        'activite' => 'stabulation', 'temperature_ambiante' => 15,
    ], $overrides));
    $aliment = new Aliment([
        'libelle0' => 'Fourrage du cas analytique PDI', 'type' => 'fourrage', 'ms' => 100,
        'ufl' => 1, 'pdi' => 100, 'mo' => 900, 'd_mo' => 200 / 3,
        'niref' => 20 / 650 * 100, 'bpr' => 0, 'mat' => 150,
        'lys_di' => 6.7, 'met_di' => 1.9, 'uel' => 1, 'prix' => 0,
    ]);
    $ration->setRelation('planRationnement', new PlanRationnement(['inra' => '2018']));
    $ration->setRelation('rationAliments', collect([
        (new RationAliment(['quantite' => 20, 'is_mb' => false, 'is_volonte' => false]))
            ->setRelation('aliment', $aliment),
    ]));
    $ration->setRelation('melanges', collect());

    return $ration;
}

test('adult dairy cows retain their nonproductive protein needs without growth', function () {
    $ration = dairyProteinAuditRation();
    expect(Apport::calculerApportMOND($ration))->toEqualWithDelta(300, 1e-9);
    expect(Apport::calculerEffPDI($ration))->toEqualWithDelta(0.67, 1e-9);

    // 202,8 g urinaires + 14,544647724 g phanères + 416,417910448 g fécaux.
    expect(Besoin::calculerBesoinPDI_NP($ration))->toEqualWithDelta(633.7625581714, 1e-8);
    expect(Besoin::calculerBesoinPDI_gain($ration))->toBe(0.0);
    expect(Besoin::calculerBesoinPDI_gest($ration))->toBe(0.0);
});

test('dairy pregnancy protein follows gestation and calf weight instead of maternal growth', function (float $age, ?float $weeks, float $expected) {
    $ration = dairyProteinAuditRation([
        'age_mois' => $age, 'parite' => $age < 40 ? 1 : 2,
        'mois_gestation' => $weeks === null ? null : $weeks / 4.348,
    ]);
    expect(Apport::calculerEffPDI($ration))->toEqualWithDelta(0.67, 1e-9);
    expect(Besoin::calculerBesoinPDI_gest($ration))->toEqualWithDelta($expected, 1e-8);
})->with([
    'adult not pregnant' => [50, 0, 0],
    'young not pregnant' => [30, 0, 0],
    'unknown gestation' => [30, null, 0],
    'adult ten weeks' => [50, 10, 9.130248542063],
    'young ten weeks' => [30, 10, 9.130248542063],
    'adult thirty weeks' => [50, 30, 84.06521921451],
]);

test('young dairy growth is counted once and does not replace maintenance', function (array $parameters, float $growth, float $total) {
    $ration = dairyProteinAuditRation($parameters);
    expect(Apport::calculerEffPDI($ration))->toEqualWithDelta(0.67, 1e-9);
    expect(Besoin::calculerBesoinPDI_NP($ration))->toEqualWithDelta(633.7625581714, 1e-8);
    expect(Besoin::calculerBesoinPDI_gain($ration))->toEqualWithDelta($growth, 1e-8);
    expect(Besoin::calculerBesoinPDI_gest($ration))->toBe(0.0);
    expect(Besoin::calculerBesoinTotalPDI($ration))->toEqualWithDelta($total, 1e-8);
})->with([
    'individual aged thirty months' => [['age_mois' => 30, 'parite' => 1], 104.776119402985, 2744.508830723439],
    'herd with thirty percent young cows' => [['age_mois' => 30, 'parite' => null, 'effectif' => 20, 'pourcentage_primipare' => 30], 31.4328358208955, 2671.165547141350],
]);

test('public dairy results include maintenance and gestation in total protein and permitted milk', function () {
    $result = RationCalculator::calculer(dairyProteinAuditRation(['mois_gestation' => 10 / 4.348]));
    expect($result['besoins'])->toMatchArray([
        'pdi_entretien' => 633.8, 'pdi_gestation' => 9.1, 'pdi_croissance' => 0.0,
        'pdi_production' => 2006.0, 'pdi_total' => 2648.9,
    ]);
    expect($result['bilans']['pdi'])->toBe(-648.9);
    expect($result['impacts']['lait_par_pdi'])->toBe(27.06);
});
