<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Services\Equations2018\Apport as Apport2018;
use App\Services\Equations2018\Besoin as Besoin2018;

/**
 * Besoins liés aux variations de réserves corporelles de la vache allaitante
 * (INRA 2018, chapitre 18 — Équations 18.5 et 18.6).
 */
function allaitanteRation(array $overrides = []): Ration
{
    $plan = PlanRationnement::factory()->create(['inra' => '2018']);
    $ration = Ration::factory()->create(array_merge([
        'plan_rationnement_id' => $plan->id,
        'categorie_animal' => 'vache_allaitante',
        'poids_vif' => 650,
        'nec' => 3,
        'mois_lactation' => 2,
        'mois_gestation' => 0,
        'ecart_variation_reserve' => 0.5,
        'pourcentage_primipare' => 0,
    ], $overrides));

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
        'quantite' => 12.0,
        'is_mb' => false,
        'is_volonte' => false,
        'ordre' => 1,
    ]);

    return $ration->load(['planRationnement', 'rationAliments.aliment', 'melanges.melangeAliments.aliment']);
}

test('PDI reserve coefficient is 130 g/kg for a fully multiparous herd (Eq. 18.6)', function () {
    // L'Éq. 18.6 est exprimée en kg PDI/j (coef 0,13) : convertie en g PDI/j, le coefficient
    // effectif vaut 130 g PDI par kg de variation de réserves (Box 18.2 : −0,1 kg/j → −13 g/j).
    $ration = allaitanteRation(['pourcentage_primipare' => 0, 'ecart_variation_reserve' => 0.5]);
    $eff = Apport2018::calculerEffPDI($ration);

    expect($eff)->toBeGreaterThan(0.0);
    // PDI_ΔBR = coef × cΔBW / PDIeff  ⇒  coef = PDI_ΔBR × PDIeff / cΔBW
    $coef = Besoin2018::calculerBesoinPDI_DRC($ration) * $eff / 0.5;
    expect($coef)->toEqualWithDelta(130.0, 0.01);
});

test('PDI reserve coefficient is 160 g/kg for a fully primiparous herd (Eq. 18.6)', function () {
    $ration = allaitanteRation(['pourcentage_primipare' => 100, 'ecart_variation_reserve' => 0.5]);
    $eff = Apport2018::calculerEffPDI($ration);

    $coef = Besoin2018::calculerBesoinPDI_DRC($ration) * $eff / 0.5;
    expect($coef)->toEqualWithDelta(160.0, 0.01);
});

test('primiparous cows need more reserve PDI than multiparous ones', function () {
    $multipare = allaitanteRation(['pourcentage_primipare' => 0]);
    $primipare = allaitanteRation(['pourcentage_primipare' => 100]);

    // Comparer les coefficients nets : chaque ration de test possède un aliment tiré séparément,
    // donc une efficience PDI potentiellement différente.
    $coefMultipare = Besoin2018::calculerBesoinPDI_DRC($multipare)
        * Apport2018::calculerEffPDI($multipare) / 0.5;
    $coefPrimipare = Besoin2018::calculerBesoinPDI_DRC($primipare)
        * Apport2018::calculerEffPDI($primipare) / 0.5;

    expect($coefPrimipare)->toBeGreaterThan($coefMultipare);
});

test('reserve PDI uses an efficiency of 1 when the cow mobilises reserves (Eq. 18.6 note)', function () {
    $ration = allaitanteRation(['pourcentage_primipare' => 0, 'ecart_variation_reserve' => -0.5]);
    $eff = Apport2018::calculerEffPDI($ration);

    // La ration a bien une efficience PDI < 1 : sans la règle, la valeur serait divisée par elle.
    expect($eff)->toBeGreaterThan(0.0)->toBeLessThan(1.0);
    // cΔBW < 0 ⇒ PDIeff = 1 : PDI_ΔBR = 130 × (-0,5) / 1 = −65 g/j (Box 18.2).
    expect(Besoin2018::calculerBesoinPDI_DRC($ration))->toEqualWithDelta(130.0 * -0.5, 0.01);
});

test('energy reserve coefficient is 2.4 multiparous and 1.8 primiparous (Eq. 18.5)', function () {
    $multipare = allaitanteRation(['pourcentage_primipare' => 0, 'ecart_variation_reserve' => 0.5]);
    $primipare = allaitanteRation(['pourcentage_primipare' => 100, 'ecart_variation_reserve' => 0.5]);

    expect(Besoin2018::calculerBesoinUF_DRC($multipare))->toEqualWithDelta(2.4 * 0.5, 0.0001);
    expect(Besoin2018::calculerBesoinUF_DRC($primipare))->toEqualWithDelta(1.8 * 0.5, 0.0001);
});
