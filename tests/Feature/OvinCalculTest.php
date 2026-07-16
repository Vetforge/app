<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Services\Equations2018\Apport as Apport2018;
use App\Services\Equations2018\Besoin as Besoin2018;
use App\Services\Equations2018\Impact as Impact2018;
use App\Services\RationCalculator;

/**
 * Cas de référence ovins tirés du chapitre 20 (INRA 2018).
 */
function ovinRation(array $overrides, float $forageMs = 1.5): Ration
{
    $plan = PlanRationnement::factory()->create(['inra' => '2018']);
    $ration = Ration::factory()->create(array_merge([
        'plan_rationnement_id' => $plan->id,
        'poids_vif' => 70,
        'nec' => 3,
    ], $overrides));

    $forage = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'ms' => 100,
        'mo' => 900,
        'mat' => 140,
        'd_mo' => 70,
        'ufv' => 0.85,
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

test('dairy ewe maintenance and milk energy follow equations 20.10 and 20.14', function () {
    $ration = ovinRation([
        'categorie_animal' => 'brebis_laitiere',
        'poids_vif' => 70,
        'lait_objectif' => 2.5,
        'mfc' => 70,
        'mpc' => 55,
        'race' => 'lacaune',
        'activite' => 'stabulation',
    ]);

    // Entretien : 0,0345 × 70^0,75 (Éq. 20.10).
    expect(Besoin2018::calculerBesoinUF_NP($ration))->toEqualWithDelta(0.0345 * 70 ** 0.75, 0.001);

    // Lait : 0,686 × sMY avec sMY = 2,5 × (0,0071×70 + 0,0043×55 + 0,2224) (Éq. 20.14).
    $smy = 2.5 * (0.0071 * 70 + 0.0043 * 55 + 0.2224);
    expect(Besoin2018::calculerBesoinUF_PL($ration))->toEqualWithDelta(0.686 * $smy, 0.001);
});

test('lacaune dairy ewe intake capacity follows equations 20.48 and 20.52', function () {
    $ration = ovinRation([
        'categorie_animal' => 'brebis_laitiere',
        'poids_vif' => 75,
        'lait_objectif' => 3.0,
        'mfc' => 70,
        'mpc' => 55,
        'race' => 'lacaune',
        'jours_lactation' => 75,
        'temperature_ambiante' => 15,
    ]);

    $smy = 3.0 * (0.0071 * 70 + 0.0043 * 55 + 0.2224);
    $correctionTemperature = 1.345 - 0.0183 * 15;
    expect(Besoin2018::calculerCapaciteIngestion($ration))
        ->toEqualWithDelta((0.900 * $smy + 0.0240 * 75) * $correctionTemperature, 0.001);
});

test('growing lamb energy matches table 20.3 (equation 20.53)', function () {
    // Table 20.3 : 20 kg + 100 g/j → 0,57 UFV ; 20 kg + 300 g/j → 0,97 UFV.
    $lamb1 = ovinRation(['categorie_animal' => 'agneau_croissance', 'poids_vif' => 20, 'gmq' => 100]);
    expect(Besoin2018::calculerBesoinTotalUF($lamb1))->toEqualWithDelta(0.01802 * 20 + 0.00205 * 100, 0.001);
    expect(Besoin2018::calculerBesoinTotalUF($lamb1))->toEqualWithDelta(0.57, 0.02);

    $lamb2 = ovinRation(['categorie_animal' => 'agneau_croissance', 'poids_vif' => 20, 'gmq' => 300]);
    expect(Besoin2018::calculerBesoinTotalUF($lamb2))->toEqualWithDelta(0.97, 0.02);

    // Ingestion de l'agneau à l'engraissement (Éq. 20.55), exprimée en kg MS/j.
    $dmi = Apport2018::calculerApportTotalMS($lamb1);
    $ufv = Apport2018::calculerApportTotalUF($lamb1) / $dmi;
    $ingestion = (37.65 + 1.98 * (100 / 20) - 18.11 * $ufv) * 20 / 1000;
    expect(Besoin2018::calculerCapaciteIngestion($lamb1))->toEqualWithDelta($ingestion, 0.001);
});

test('suckling ewe milk energy follows equation 20.13', function () {
    $ration = ovinRation([
        'categorie_animal' => 'brebis_allaitante',
        'poids_vif' => 65,
        'nec' => 3,
        'jours_lactation' => 21,     // 3 semaines
        'gmq_portee' => 300,         // ADGlit = 0,3 kg/j
        'nombre_jeunes' => 2,
        'poids_portee' => 8,
    ]);

    $dim = 21.0;
    $adglit = 0.3;
    $attendu = -0.0274 * $adglit * $dim - 0.0007 * $dim + 3.66 * $adglit + 0.0602;
    expect(Besoin2018::calculerBesoinUF_PL($ration))->toEqualWithDelta($attendu, 0.001);
});

test('dairy ewe milk permitted inverts the ovin energy and protein requirements (ch. 20)', function () {
    $ration = ovinRation([
        'categorie_animal' => 'brebis_laitiere',
        'poids_vif' => 70,
        'lait_objectif' => 2.5,
        'mfc' => 70,
        'mpc' => 55,
        'race' => 'lacaune',
        'jours_lactation' => 100,
    ]);

    // Coût énergétique du lait par litre : 0,686 × (0,0071×MFC + 0,0043×MPC + 0,2224) (Éq. 20.14).
    $coutUFL = Besoin2018::calculerBesoinUF_PL($ration) / 2.5;
    expect($coutUFL)->toEqualWithDelta(0.686 * (0.0071 * 70 + 0.0043 * 55 + 0.2224), 0.001);

    $apportUFL = Apport2018::calculerApportTotalUF($ration);
    $besoinNonLaitUFL = Besoin2018::calculerBesoinTotalUF($ration) - Besoin2018::calculerBesoinUF_PL($ration);
    $attenduUFL = ($apportUFL - $besoinNonLaitUFL) / $coutUFL;
    expect(Impact2018::calculerLaitPermisParUF($ration))->toEqualWithDelta($attenduUFL, 0.001);

    // Coût protéique du lait par litre : MPC / PDIeff (0,58 en lactation, Éq. 20.24).
    $coutPDI = Besoin2018::calculerBesoinPDI_PL($ration) / 2.5;
    expect($coutPDI)->toEqualWithDelta(55 / 0.58, 0.01);
    $apportPDI = Apport2018::calculerApportTotalPDI($ration);
    $besoinNonLaitPDI = Besoin2018::calculerBesoinTotalPDI($ration) - Besoin2018::calculerBesoinPDI_PL($ration);
    $attenduPDI = ($apportPDI - $besoinNonLaitPDI) / $coutPDI;
    expect(Impact2018::calculerLaitPermisParPDI($ration))->toEqualWithDelta($attenduPDI, 0.001);
});

test('dairy ewe ration exposes species-specific milk economy impacts', function () {
    $resultats = RationCalculator::calculer(ovinRation([
        'categorie_animal' => 'brebis_laitiere',
        'poids_vif' => 70,
        'lait_objectif' => 2.5,
        'mfc' => 70,
        'mpc' => 55,
        'race' => 'lacaune',
        'jours_lactation' => 100,
    ]));

    expect($resultats['impacts'])->toHaveKeys(['lait_par_ufl', 'lait_par_pdi', 'lait_limitant']);
    expect($resultats['impacts']['lait_limitant'])
        ->toEqualWithDelta(min($resultats['impacts']['lait_par_ufl'], $resultats['impacts']['lait_par_pdi']), 0.001);
    // L'eau bue et le bilan UFL de production sont des régressions bovines : non exposés pour les ovins.
    expect($resultats['impacts'])->not->toHaveKey('eau_bue');
    expect($resultats['impacts'])->not->toHaveKey('bil_ufl');
    expect($resultats['impacts'])->not->toHaveKey('production_lait_attendue');
});

test('sheep rations use UEM/UFV without bovine milk impacts', function () {
    $resultats = RationCalculator::calculer(
        ovinRation(['categorie_animal' => 'agneau_croissance', 'poids_vif' => 30, 'gmq' => 250])
    );

    expect($resultats['besoins']['uf_total'])->toBeGreaterThan(0.0);
    expect($resultats['besoins']['ci'])->toBeGreaterThan(0.0);
    expect($resultats['impacts'])->not->toHaveKey('lait_par_ufl');
    expect($resultats['impacts'])->not->toHaveKey('production_lait_attendue');
    // L'apport énergétique de l'agneau est exprimé en UFV (colonne ufv du fourrage = 0,85 ;
    // valeur arrondie à 2 décimales dans la sortie).
    expect($resultats['apports']['ufl'])->toEqualWithDelta(0.85 * 1.5, 0.01);
});

test('growing lamb mineral gain uses the adult ewe reference weight, not a hardcoded 60 kg', function () {
    $ration = ovinRation(['categorie_animal' => 'agneau_croissance', 'poids_vif' => 30, 'gmq' => 250]);
    $dmi = Apport2018::calculerApportTotalMS($ration);
    $bw = 30.0;
    $adg = 250.0;
    $bwAdult = 70.0; // référence brebis adulte (enum), bornée à au moins le poids courant

    // Éq. 20.30 : Caabsreq = (0,67·DMI + 0,01·BW) + (6,75·BWadult^0,28·BW^-0,28)/1000·ADG.
    $attendu = (0.67 * $dmi + 0.01 * $bw)
        + (6.75 * $bwAdult ** 0.28 * $bw ** -0.28) / 1000 * $adg;

    expect(Besoin2018::calculerBesoinCaabs($ration))->toEqualWithDelta($attendu, 0.001);
});
