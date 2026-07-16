<?php

use App\Models\Aliment;
use App\Models\Melange;
use App\Models\MelangeAliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Services\Equations2007\Apport as Apport2007;
use App\Services\Equations2007\Besoin as Besoin2007;
use App\Services\Equations2007\Impact as Impact2007;
use App\Services\Equations2018\Apport as Apport2018;
use App\Services\Equations2018\Besoin as Besoin2018;
use App\Services\Equations2018\CalculValeur as CalculValeur2018;
use App\Services\Equations2018\Impact as Impact2018;
use App\Services\RationCalculator;
use App\Services\RationHelper;

function createCalculationRation(string $inra = '2018'): Ration
{
    $plan = PlanRationnement::factory()->create([
        'inra' => $inra,
    ]);

    return Ration::factory()->create([
        'plan_rationnement_id' => $plan->id,
        'categorie_animal' => 'vacheLaitiere',
        'lait_objectif' => 30,
        'lait_potentiel305j' => 9000,
        'poids_vif' => 650,
        'pourcentage_primipare' => 30,
        'tb_annuel' => 40,
        'tp_annuel' => 32,
        'mois_lactation' => 4.0,
        'mois_gestation' => 2.0,
        'temperature_ambiante' => 18,
        'poids_veau_naissance' => 45,
        'ecart_variation_reserve' => -0.5,
    ]);
}

function addReference2018Ingredients(Ration $ration): void
{
    $fourrage = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'libelle0' => 'Foins',
        'libelle1' => 'Prairie',
        'ms' => 85,
        'mo' => 900,
        'mat' => 140,
        'cb' => 260,
        'ndf' => 480,
        'adf' => 290,
        'ag' => 25,
        'eb' => 4200,
        'amidon' => 40,
        'pf' => 12,
        'd_mo' => 68,
        'dt6_n' => 72,
        'dr_n' => 85,
        'dt6_ami' => 50,
        'ca' => 8,
        'p' => 3,
        'mg' => 2.4,
        'na' => 1.5,
        'k' => 22,
        'cl' => 5,
        's' => 2,
        'co' => 0.1,
        'se' => 0.02,
        'zn' => 40,
        'mn' => 95,
        'cu' => 5,
        'i' => 0.1,
        'vit_a' => 1500,
        'vit_d' => 5,
        'vit_e' => 10,
        'niref' => 1.8,
        'b_vec' => 0.78,
        'lys_di' => 6.8,
        'met_di' => 2.2,
    ]);

    $concentre = Aliment::factory()->systemique()->create([
        'type' => 'Conc',
        'libelle0' => 'Cereales',
        'libelle1' => 'Mais grain',
        'ms' => 88,
        'mo' => 970,
        'mat' => 180,
        'cb' => 70,
        'ndf' => 180,
        'adf' => 90,
        'ag' => 35,
        'eb' => 4350,
        'amidon' => 320,
        'pf' => 6,
        'd_mo' => 88,
        'dt6_n' => 78,
        'dr_n' => 88,
        'dt6_ami' => 86,
        'ca' => 1.2,
        'p' => 4.1,
        'mg' => 1.6,
        'na' => 2.2,
        'k' => 13,
        'cl' => 3,
        's' => 1.5,
        'co' => 0.15,
        'se' => 0.04,
        'zn' => 55,
        'mn' => 60,
        'cu' => 9,
        'i' => 0.2,
        'vit_a' => 2200,
        'vit_d' => 15,
        'vit_e' => 35,
        'niref' => 2.0,
        'b_vec' => 0.94,
        'lys_di' => 6.7,
        'met_di' => 2.0,
    ]);

    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $fourrage->id,
        'quantite' => 12,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 1,
    ]);

    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $concentre->id,
        'quantite' => 4,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 2,
    ]);
}

test('ration calculator exposes protein and health indicators for inra 2018 results', function () {
    $plan = PlanRationnement::factory()->create([
        'inra' => '2018',
    ]);

    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id,
        'categorie_animal' => 'vacheLaitiere',
        'lait_objectif' => 30,
        'lait_potentiel305j' => 9000,
        'poids_vif' => 650,
        'pourcentage_primipare' => 30,
        'tb_annuel' => 40,
        'tp_annuel' => 32,
        'mois_lactation' => 4.0,
        'mois_gestation' => 2.0,
        'temperature_ambiante' => 18,
        'poids_veau_naissance' => 45,
        'ecart_variation_reserve' => -0.5,
    ]);

    $fourrage = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'libelle0' => 'Foins',
        'libelle1' => 'Prairie',
        'ms' => 85,
        'mo' => 900,
        'mat' => 140,
        'cb' => 260,
        'ndf' => 480,
        'adf' => 290,
        'ag' => 25,
        'eb' => 4200,
        'amidon' => 40,
        'pf' => 12,
        'd_mo' => 68,
        'dt6_n' => 72,
        'dr_n' => 85,
        'dt6_ami' => 50,
        'ca' => 8,
        'p' => 3,
        'mg' => 2.4,
        'na' => 1.5,
        'k' => 22,
        'cl' => 5,
        's' => 2,
        'co' => 0.1,
        'se' => 0.02,
        'zn' => 40,
        'mn' => 95,
        'cu' => 5,
        'i' => 0.1,
        'vit_a' => 1500,
        'vit_d' => 5,
        'vit_e' => 10,
        'niref' => 1.8,
        'b_vec' => 0.78,
        'lys_di' => 6.8,
        'met_di' => 2.2,
    ]);

    $concentre = Aliment::factory()->systemique()->create([
        'type' => 'Conc',
        'libelle0' => 'Cereales',
        'libelle1' => 'Mais grain',
        'ms' => 88,
        'mo' => 970,
        'mat' => 180,
        'cb' => 70,
        'ndf' => 180,
        'adf' => 90,
        'ag' => 35,
        'eb' => 4350,
        'amidon' => 320,
        'pf' => 6,
        'd_mo' => 88,
        'dt6_n' => 78,
        'dr_n' => 88,
        'dt6_ami' => 86,
        'ca' => 1.2,
        'p' => 4.1,
        'mg' => 1.6,
        'na' => 2.2,
        'k' => 13,
        'cl' => 3,
        's' => 1.5,
        'co' => 0.15,
        'se' => 0.04,
        'zn' => 55,
        'mn' => 60,
        'cu' => 9,
        'i' => 0.2,
        'vit_a' => 2200,
        'vit_d' => 15,
        'vit_e' => 35,
        'niref' => 2.0,
        'b_vec' => 0.94,
        'lys_di' => 6.7,
        'met_di' => 2.0,
    ]);

    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $fourrage->id,
        'quantite' => 12,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 1,
    ]);

    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $concentre->id,
        'quantite' => 4,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 2,
    ]);

    $ration->load([
        'planRationnement',
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
    ]);

    $resultats = RationCalculator::calculer($ration);
    $indicateurs = $resultats['indicateurs'];

    expect($resultats['inra'])->toBe('2018');
    expect($indicateurs['pdi_par_kg_ms'])->toBe(round(Apport2018::calculerApportPDIParKgMS($ration), 2));
    expect($indicateurs['bpr'])->toBe(round(Apport2018::calculerBPR($ration), 2));
    expect($indicateurs['be'])->toBe(round(Impact2018::calculerBE($ration), 1));
    expect($indicateurs['mod_concentre'])->toBe(round(Apport2018::calculerApportConcentreMOD($ration), 1));
    expect($indicateurs['amid_ru'])->toBe(round(Apport2018::calculerApportAmiD_ru($ration), 1));
    expect($indicateurs['pco_percent'])->toBe(round(Apport2018::calculerPCO($ration) * 100, 1));
    expect($indicateurs['ndf_fourrages'])->toBe(round(Apport2018::calculerApportNDFf($ration), 1));
    expect($indicateurs['ndf_total'])->toBe(round(Apport2018::calculerApportNDFParKgMS($ration), 1));
    expect($indicateurs['cb_par_kg_ms'])->toBe(round(Apport2018::calculerApportCBParKgMS($ration), 1));
    expect($indicateurs['adf_par_kg_ms'])->toBe(round(Apport2018::calculerApportADFParKgMS($ration), 1));
    expect($indicateurs['ufl_par_kg_ms'])->toBe(round(Apport2018::calculerApportUFLParKgMS($ration), 2));
    expect($indicateurs['baca'])->toBe(round(Impact2018::calculerBACA($ration), 1));
    expect($indicateurs['prod_agvt_jour'])->toBe(round(Apport2018::calculerProdAGVT($ration) * Apport2018::calculerApportTotalMS($ration), 2));
    expect($indicateurs['acetate'])->toBe(round(Impact2018::calculerPourcentageAcetate($ration), 1));
    expect($indicateurs['propionate'])->toBe(round(Impact2018::calculerPourcentagePropionate($ration), 1));
    expect($indicateurs['butyrate'])->toBe(round(Impact2018::calculerPourcentageButyrate($ration), 1));
    expect($indicateurs['ira'])->toBe(round(Impact2018::calculerIRA($ration), 2));
    expect($indicateurs['ph_ruminal'])->toBe(round(Impact2018::calculerPH($ration), 2));
    expect($indicateurs['azote_urinaire'])->toBe(round(Impact2018::calculerNU($ration), 1));
    expect($indicateurs['azote_fecale'])->toBe(round(Impact2018::calculerNND($ration), 1));
    expect($resultats['impacts']['ch4'])->toBe(round(Impact2018::calculerCH4($ration), 1));
    expect($resultats['apports']['co'])->toBe(round(Apport2018::calculerApportCo($ration), 1));
    expect($resultats['apports']['vit_a'])->toBe(round(Apport2018::calculerApportVitA($ration), 0));
    expect($resultats['besoins']['mgabs'])->toBe(round(Besoin2018::calculerBesoinMgabs($ration), 1));
    expect($resultats['besoins']['na'])->toBe(round(Besoin2018::calculerBesoinNa($ration), 1));
    expect($resultats['besoins']['co'])->toBe(round(Besoin2018::calculerBesoinCo($ration), 1));
    expect($resultats['supplementations']['vit_e'])->toBe(round(Besoin2018::calculerSupplementationVitE($ration), 0));

    $expectedMAmicDuo = 12 * (new CalculValeur2018($ration, $fourrage))->calculerMAmic_duoAliment()
        / Apport2018::calculerApportTotalMS($ration);

    expect(Apport2018::calculerMAmic_duo($ration))->toBe($expectedMAmicDuo);
    expect(Apport2018::calculerDTAmi($ration))->toBe(
        Apport2018::calculerApportAmiD_ru($ration) * 100 / Apport2018::calculerApportAmidon($ration)
    );
});

test('inra 2018 normalizes legacy activity and race aliases', function () {
    $ration = createCalculationRation();

    $ration->categorie_animal = 'vacheLaitiere';
    $ration->activite = 'normale';
    $normalisee = $ration->replicate();
    $normalisee->activite = 'stabulation';

    expect(Besoin2018::calculerBesoinUF_NP($ration))->toBe(Besoin2018::calculerBesoinUF_NP($normalisee));

    $rationAllaitante = $ration->replicate();
    $rationAllaitante->categorie_animal = 'vacheAllaitante';
    $rationAllaitante->race = 'croiseLaitiere';
    $rationAllaitante->mois_lactation = 2.0;
    $rationAllaitante->mois_gestation = 0.0;
    $rationAllaitante->lait_objectif = 10.0;

    $raceNormalisee = $rationAllaitante->replicate();
    $raceNormalisee->race = 'croiselaitiere';

    expect(Besoin2018::calculerCapaciteIngestion($rationAllaitante))->toBe(Besoin2018::calculerCapaciteIngestion($raceNormalisee));
});

test('inra 2018 exposes uterine PDI bonus during the first two lactation weeks', function () {
    $ration = createCalculationRation();

    $ration->mois_lactation = 0.23;
    expect(RationHelper::calculerPDIUt($ration))->toBe(100.0);

    $ration->mois_lactation = 0.46;
    expect(RationHelper::calculerPDIUt($ration))->toBe(50.0);

    $ration->mois_lactation = 0.70;
    expect(RationHelper::calculerPDIUt($ration))->toBe(0.0);
});

test('inra 2018 limiting milk is not capped by objective and exposes expected milk separately', function () {
    $ration = createCalculationRation('2018');
    $ration->update([
        'lait_objectif' => 12,
        'lait_potentiel305j' => 10500,
        'mois_lactation' => 3.5,
        'mois_gestation' => 1.0,
    ]);

    addReference2018Ingredients($ration);

    $ration->load([
        'planRationnement',
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
    ]);

    $resultats = RationCalculator::calculer($ration);
    $expectedLaitParPDI = Impact2018::calculerLaitPermisParPDI($ration);
    $expectedLaitLimitant = min(Impact2018::calculerLaitPermisParUF($ration), $expectedLaitParPDI);
    $expectedProductionAttendue = Impact2018::calculerPL($ration);

    expect($resultats['impacts']['lait_par_pdi'])->toBe(round($expectedLaitParPDI, 2));
    expect($resultats['impacts']['lait_limitant'])->toBe(round($expectedLaitLimitant, 2));
    expect($resultats['impacts']['lait_limitant'])->toBeGreaterThan($ration->lait_objectif);
    expect($resultats['impacts']['production_lait_attendue'])->toBe(round($expectedProductionAttendue, 2));
    expect($resultats['indicateurs']['pl_pot'])->toBe(round(RationHelper::calculerProductionLaitPotentielle($ration), 2));
});

test('mix ingredients are scaled by mix quantity and ignored when the mix quantity is null or zero', function () {
    $ration = createCalculationRation();

    $directFourrage = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'ms' => 100,
        'p' => 1,
        'prix' => 10,
    ]);

    $mixIngredientA = Aliment::factory()->systemique()->create([
        'type' => 'Conc',
        'ms' => 100,
        'p' => 2,
        'prix' => 20,
    ]);

    $mixIngredientB = Aliment::factory()->systemique()->create([
        'type' => 'Conc',
        'ms' => 100,
        'p' => 4,
        'prix' => 30,
    ]);

    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $directFourrage->id,
        'quantite' => 5,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 1,
    ]);

    $melange = Melange::create([
        'ration_id' => $ration->id,
        'nom' => 'Mix test',
        'quantite' => 10,
        'is_volonte' => false,
        'is_mb' => false,
        'ordre' => 1,
    ]);

    MelangeAliment::create([
        'melange_id' => $melange->id,
        'aliment_id' => $mixIngredientA->id,
        'quantite' => 2,
        'is_mb' => false,
        'ordre' => 1,
    ]);

    MelangeAliment::create([
        'melange_id' => $melange->id,
        'aliment_id' => $mixIngredientB->id,
        'quantite' => 3,
        'is_mb' => false,
        'ordre' => 2,
    ]);

    $ration->load([
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
    ]);

    expect(Apport2018::calculerApportTotalMS($ration))->toBe(15.0);
    expect(Apport2018::calculerApportP($ration))->toBe(37.0);
    expect(Apport2018::calculerCoutParAnimal($ration))->toBe(310.0);
    expect(Apport2007::calculerApportTotalMS($ration))->toBe(15.0);
    expect(Apport2007::calculerCoutParAnimal($ration))->toBe(310.0);

    $melange->update(['quantite' => 0]);
    Apport2018::clearCache();
    Apport2007::clearCache();
    $ration->load([
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
    ]);

    expect(Apport2018::calculerApportTotalMS($ration))->toBe(5.0);
    expect(Apport2018::calculerApportP($ration))->toBe(5.0);
    expect(Apport2018::calculerCoutParAnimal($ration))->toBe(50.0);
    expect(Apport2007::calculerApportTotalMS($ration))->toBe(5.0);
    expect(Apport2007::calculerCoutParAnimal($ration))->toBe(50.0);

    $melange->update(['quantite' => null]);
    Apport2018::clearCache();
    Apport2007::clearCache();
    $ration->load([
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
    ]);

    expect(Apport2018::calculerApportTotalMS($ration))->toBe(5.0);
    expect(Apport2018::calculerApportP($ration))->toBe(5.0);
    expect(Apport2018::calculerCoutParAnimal($ration))->toBe(50.0);
    expect(Apport2007::calculerApportTotalMS($ration))->toBe(5.0);
    expect(Apport2007::calculerCoutParAnimal($ration))->toBe(50.0);
});

test('water intake includes forage protein carried by mixes', function () {
    $ration = createCalculationRation();

    $fourrage = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'ms' => 85,
        'mat' => 140,
    ]);

    $melange = Melange::create([
        'ration_id' => $ration->id,
        'nom' => 'Mélange fourrage',
        'quantite' => 10,
        'is_volonte' => false,
        'is_mb' => false,
        'ordre' => 1,
    ]);

    MelangeAliment::create([
        'melange_id' => $melange->id,
        'aliment_id' => $fourrage->id,
        'quantite' => 1,
        'is_mb' => false,
        'ordre' => 1,
    ]);

    $ration->load([
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
    ]);

    $apportTotalMS = Apport2018::calculerApportTotalMS($ration);
    $pourcentageMSParMB = Apport2018::calculerApportMSParMB($ration) * 100;
    $proportionFourrage = Apport2018::calculerApportFourragesMS($ration) / $apportTotalMS;
    $matFourrages = Apport2018::calculerApportFourragesMAT($ration) / $apportTotalMS;
    $expected = Impact2018::calculerEauBueTH($ration)
        - 4.34
        + 0.88 * (float) ($ration->lait_objectif ?? 0)
        + $apportTotalMS * (4.6 - 100 / $pourcentageMSParMB)
        + 0.0012 * pow($matFourrages * $proportionFourrage, 2);

    expect($matFourrages)->toBe(140.0);
    expect(round(Impact2018::calculerEauBue($ration), 6))->toBe(round($expected, 6));
});

test('mix MB flag scales ingredient contributions by recipe MB while preserving each ingredient ratio', function () {
    $ration = createCalculationRation();

    $ingredientA = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'ms' => 50,
        'p' => 10,
    ]);

    $ingredientB = Aliment::factory()->systemique()->create([
        'type' => 'Conc',
        'ms' => 100,
        'p' => 20,
    ]);

    $melange = Melange::create([
        'ration_id' => $ration->id,
        'nom' => 'Mix MB',
        'quantite' => 10,
        'is_volonte' => false,
        'is_mb' => false,
        'ordre' => 1,
    ]);

    MelangeAliment::create([
        'melange_id' => $melange->id,
        'aliment_id' => $ingredientA->id,
        'quantite' => 1,
        'is_mb' => false,
        'ordre' => 1,
    ]);

    MelangeAliment::create([
        'melange_id' => $melange->id,
        'aliment_id' => $ingredientB->id,
        'quantite' => 1,
        'is_mb' => false,
        'ordre' => 2,
    ]);

    $ration->load([
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
    ]);

    expect(Apport2018::calculerApportTotalMS($ration))->toBe(10.0);
    expect(Apport2018::calculerApportP($ration))->toBe(150.0);

    $melange->update(['is_mb' => true]);
    Apport2018::clearCache();
    $ration->load([
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
    ]);

    expect(round(Apport2018::calculerApportTotalMS($ration), 2))->toBe(6.67);
    expect(round(Apport2018::calculerApportP($ration), 2))->toBe(100.0);
});

test('mix ingredient MB flag updates recipe weighting ingredient by ingredient', function () {
    $ration = createCalculationRation();

    $ingredientA = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'ms' => 50,
        'p' => 10,
    ]);

    $ingredientB = Aliment::factory()->systemique()->create([
        'type' => 'Conc',
        'ms' => 100,
        'p' => 20,
    ]);

    $melange = Melange::create([
        'ration_id' => $ration->id,
        'nom' => 'Mix ingredient MB',
        'quantite' => 10,
        'is_volonte' => false,
        'is_mb' => true,
        'ordre' => 1,
    ]);

    $melangeAlimentA = MelangeAliment::create([
        'melange_id' => $melange->id,
        'aliment_id' => $ingredientA->id,
        'quantite' => 10,
        'is_mb' => false,
        'ordre' => 1,
    ]);

    MelangeAliment::create([
        'melange_id' => $melange->id,
        'aliment_id' => $ingredientB->id,
        'quantite' => 10,
        'is_mb' => false,
        'ordre' => 2,
    ]);

    $ration->load([
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
    ]);

    expect(round(Apport2018::calculerApportTotalMS($ration), 2))->toBe(6.67);
    expect(round(Apport2018::calculerApportP($ration), 2))->toBe(100.0);

    $melangeAlimentA->update(['is_mb' => true]);
    Apport2018::clearCache();
    $ration->load([
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
    ]);

    expect(round(Apport2018::calculerApportTotalMS($ration), 2))->toBe(7.5);
    expect(round(Apport2018::calculerApportP($ration), 2))->toBe(125.0);
});

test('inra 2007 formulas', function () {
    $ration = createCalculationRation('2007');
    $ration->update([
        'categorie_animal' => 'vacheAllaitante',
        'poids_vif' => 700,
        'pourcentage_primipare' => 25,
        'nec' => 3,
        'lait_objectif' => 10,
        'mois_lactation' => 0,
        'mois_gestation' => 0,
        'activite' => 'stabulation',
        'race' => 'charolaise',
        'strategie' => 'bonne',
        'temperature_ambiante' => 15,
    ]);

    $fourrage = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'ms' => 100,
        'mat' => 120,
        'cb' => 250,
        'ndf' => 360,
        'adf' => 180,
        'amidon' => 30,
        'p' => 4,
        'pabs' => 1.5,
        'mg' => 2,
        'k' => 15,
        'na' => 1,
        'ca' => 6,
        'ufl2007' => 0.8,
        'uel2007' => 1.1,
        'ueb2007' => 0.6,
        'pdie2007' => 90,
        'pdin2007' => 80,
    ]);

    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $fourrage->id,
        'quantite' => 10,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 1,
    ]);

    $ration->load([
        'planRationnement',
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
    ]);

    $resultats = RationCalculator::calculer($ration);

    $expectedUfEntretien = ((1.1 * 0.037) + (0.0068 * (3 - 2.5))) * pow(700, 0.75);
    $expectedUfProduction = 0.45 * 10;
    $expectedCapaciteIngestion = 1 * 0.95 * (3.2 + (0.015 * 700) + (0.25 * 10) - (0.002 * 700 * (3 - 2.5))) * (1 - (0.12 * 0.25));
    $expectedPdiEntretien = 3.25 * pow(700, 0.75);
    $expectedPdiProduction = 53 * 10;
    $expectedPdiCroissance = 80 * 0.25;
    $expectedPdiGestation = 0.07 * 45 * exp(0.111 * 0);
    $expectedPdiTotal = $expectedPdiEntretien + $expectedPdiProduction + $expectedPdiCroissance + $expectedPdiGestation + 300;
    $expectedMgabs = (0.011 * 700) + (0.15 * 10);

    expect($resultats['besoins']['uf_entretien'])->toBe(round($expectedUfEntretien, 2));
    expect($resultats['besoins']['uf_production'])->toBe(round($expectedUfProduction, 2));
    expect($resultats['besoins']['ci'])->toBe(round($expectedCapaciteIngestion, 2));
    expect($resultats['besoins']['pdi_entretien'])->toBe(round($expectedPdiEntretien, 1));
    expect($resultats['besoins']['pdi_production'])->toBe(round($expectedPdiProduction, 1));
    expect($resultats['besoins']['pdi_total'])->toBe(round($expectedPdiTotal, 1));
    expect($resultats['besoins']['mgabs'])->toBe(round($expectedMgabs, 1));
    expect($resultats['apports']['pabs'])->toBe(15.0);
    expect($resultats['bilans']['pabs'])->toBe(round(15.0 - Besoin2007::calculerBesoinPabs($ration), 1));
    expect($resultats['indicateurs']['pdie_par_kg_ms'])->toBe(round(Apport2007::calculerApportPDIEParKgMS($ration), 2));
    expect($resultats['indicateurs']['pdin_min'])->toBe(round(Impact2007::calculerMinimumPDIN($ration), 1));
    expect($resultats['indicateurs']['pdin_max'])->toBe(round(Impact2007::calculerMaximumPDIN($ration), 1));
});

test('inra 2007 uses 2007 UE fields and includes forage protein from mixes in water intake', function () {
    $ration = createCalculationRation('2007');
    $ration->update([
        'categorie_animal' => 'vacheAllaitante',
        'lait_objectif' => 0,
        'temperature_ambiante' => 15,
    ]);

    $directFourrage = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'ms' => 100,
        'mat' => 100,
        'uel2007' => 2.0,
        'ueb2007' => 0.5,
    ]);

    $mixFourrage = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'ms' => 100,
        'mat' => 200,
        'uel2007' => 3.0,
        'ueb2007' => 0.8,
    ]);

    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $directFourrage->id,
        'quantite' => 10,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 1,
    ]);

    $melange = Melange::create([
        'ration_id' => $ration->id,
        'nom' => 'Mix fourrage 2007',
        'quantite' => 5,
        'is_volonte' => false,
        'is_mb' => false,
        'ordre' => 1,
    ]);

    MelangeAliment::create([
        'melange_id' => $melange->id,
        'aliment_id' => $mixFourrage->id,
        'quantite' => 1,
        'is_mb' => false,
        'ordre' => 1,
    ]);

    $ration->load([
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
    ]);

    $expectedWater = -4.34
        + Apport2007::calculerApportTotalMS($ration) * (4.6 - 100 / (Apport2007::calculerApportMSParMB($ration) * 100))
        + 0.0012 * pow(Apport2007::calculerApportFourragesMAT($ration) / Apport2007::calculerApportTotalMS($ration), 2);

    expect(Apport2007::calculerApportFourragesUE($ration))->toBe(9.0);
    expect(Apport2007::calculerApportFourragesMAT($ration))->toBe(2000.0);
    expect(round(Impact2007::calculerEauBue($ration), 6))->toBe(round($expectedWater, 6));
});

test('inra 2018 result outputs expose limiting milk, expected milk and bil ufl logic', function () {
    $ration = createCalculationRation('2018');

    $fourrage = Aliment::factory()->systemique()->create([
        'type' => 'Fourrage',
        'ms' => 85,
        'mo' => 900,
        'mat' => 140,
        'cb' => 260,
        'ndf' => 480,
        'adf' => 290,
        'ag' => 25,
        'eb' => 4200,
        'amidon' => 40,
        'pf' => 12,
        'd_mo' => 68,
        'dt6_n' => 72,
        'dr_n' => 85,
        'dt6_ami' => 50,
        'ca' => 8,
        'p' => 3,
        'mg' => 2.4,
        'na' => 1.5,
        'k' => 22,
        'cl' => 5,
        's' => 2,
        'co' => 0.1,
        'se' => 0.02,
        'zn' => 40,
        'mn' => 95,
        'cu' => 5,
        'i' => 0.1,
        'vit_a' => 1500,
        'vit_d' => 5,
        'vit_e' => 10,
        'niref' => 1.8,
        'b_vec' => 0.78,
        'lys_di' => 6.8,
        'met_di' => 2.2,
    ]);

    $concentre = Aliment::factory()->systemique()->create([
        'type' => 'Conc',
        'ms' => 88,
        'mo' => 970,
        'mat' => 180,
        'cb' => 70,
        'ndf' => 180,
        'adf' => 90,
        'ag' => 35,
        'eb' => 4350,
        'amidon' => 320,
        'pf' => 6,
        'd_mo' => 88,
        'dt6_n' => 78,
        'dr_n' => 88,
        'dt6_ami' => 86,
        'ca' => 1.2,
        'p' => 4.1,
        'mg' => 1.6,
        'na' => 2.2,
        'k' => 13,
        'cl' => 3,
        's' => 1.5,
        'co' => 0.15,
        'se' => 0.04,
        'zn' => 55,
        'mn' => 60,
        'cu' => 9,
        'i' => 0.2,
        'vit_a' => 2200,
        'vit_d' => 15,
        'vit_e' => 35,
        'niref' => 2.0,
        'b_vec' => 0.94,
        'lys_di' => 6.7,
        'met_di' => 2.0,
    ]);

    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $fourrage->id,
        'quantite' => 12,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 1,
    ]);

    RationAliment::create([
        'ration_id' => $ration->id,
        'aliment_id' => $concentre->id,
        'quantite' => 4,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 2,
    ]);

    $ration->load([
        'planRationnement',
        'rationAliments.aliment',
        'melanges.melangeAliments.aliment',
    ]);

    $resultats = RationCalculator::calculer($ration);
    $expectedLaitParPDI = Impact2018::calculerLaitPermisParPDI($ration);
    $expectedLaitLimitant = min(Impact2018::calculerLaitPermisParUF($ration), $expectedLaitParPDI);
    $expectedProductionAttendue = Impact2018::calculerPL($ration);
    $expectedRapportCN = 14.2 + 52.7 * exp(-0.014 * Apport2018::calculerApportMAT($ration)) - 3.74 * Apport2018::calculerPCO($ration);

    expect($resultats['impacts']['lait_par_pdi'])->toBe(round($expectedLaitParPDI, 2));
    expect($resultats['impacts']['lait_limitant'])->toBe(round($expectedLaitLimitant, 2));
    expect($resultats['impacts']['production_lait_attendue'])->toBe(round($expectedProductionAttendue, 2));
    expect($resultats['impacts']['bil_ufl'])->toBe(round(Impact2018::calculerBilUFL($ration), 2));
    expect(round(Impact2018::calculerRapportCN($ration), 6))->toBe(round($expectedRapportCN, 6));
    expect(Besoin2018::calculerBesoinMo($ration))->toBe(0.5 * Apport2018::calculerApportTotalMS($ration));
    expect(Apport2018::calculerApportPDIAParKgMS($ration))->toBe(
        Apport2018::calculerApportTotalPDIA($ration) / Apport2018::calculerApportTotalMS($ration)
    );
});

test('inra 2018 potassium and chlorine requirements follow table 8.1', function () {
    // Vache laitière en lactation, hors dernier tiers de gestation.
    $lactante = createCalculationRation();
    $lactante->poids_vif = 650.0;
    $lactante->lait_objectif = 30.0;
    $lactante->mois_gestation = 2.0;

    $semGestLactante = RationHelper::calculerSemainesGestation($lactante);
    expect($semGestLactante)->toBeLessThan(27.0);

    // Table 8.1 : K entretien lactation = 0.150 × PV, lactation = 1.5 g/L, dernier tiers = +1.0.
    expect(Besoin2018::calculerBesoinK($lactante))->toBe(0.150 * 650.0 + 1.5 * 30.0);
    // Table 8.1 : Cl entretien lactation = 0.035 × PV, lactation = 1.15 g/L, dernier tiers = +1.0.
    expect(Besoin2018::calculerBesoinCl($lactante))->toBe(0.035 * 650.0 + 1.15 * 30.0);

    // Vache tarie (non lactante) dans le dernier tiers de gestation.
    $tarie = createCalculationRation();
    $tarie->poids_vif = 700.0;
    $tarie->lait_objectif = 0.0;
    $tarie->mois_gestation = 8.0;

    $semGestTarie = RationHelper::calculerSemainesGestation($tarie);
    expect($semGestTarie)->toBeGreaterThanOrEqual(27.0);

    // Table 8.1 : K entretien hors lactation = 0.105 × PV, + terme dernier tiers de gestation.
    expect(Besoin2018::calculerBesoinK($tarie))->toBe(0.105 * 700.0 + 1.0);
    // Table 8.1 : Cl entretien hors lactation = 0.023 × PV, + terme dernier tiers de gestation.
    expect(Besoin2018::calculerBesoinCl($tarie))->toBe(0.023 * 700.0 + 1.0);
});
