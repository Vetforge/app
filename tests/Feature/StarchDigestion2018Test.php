<?php

declare(strict_types=1);

use App\Models\Aliment;
use App\Models\Melange;
use App\Models\MelangeAliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Services\Equations2018\Apport;
use App\Services\Equations2018\CalculValeur;
use App\Services\Equations2018\Impact;
use App\Services\RationCalculator;

/** Cas analytique : NI=2, PCO=0,4 ; 6 kg MS de fourrage et 4 kg MS de concentré. */
function starchDigestionRation(float $scale = 1.0, string $mode = 'MS'): Ration
{
    $ration = new Ration([
        'categorie_animal' => 'vache_laitiere', 'poids_vif' => 500 * $scale,
        'effectif' => 1, 'parite' => 2, 'age_mois' => 50, 'pourcentage_primipare' => 0,
        'lait_objectif' => 20, 'tb_annuel' => 40, 'tp_annuel' => 32,
        'mois_lactation' => 5, 'mois_gestation' => 0, 'nec' => 2.5,
        'nec_velage' => null, 'ecart_variation_reserve' => 0,
        'activite' => 'stabulation', 'temperature_ambiante' => 15,
    ]);
    $items = collect();
    $recipe = collect();
    foreach ([['fourrage', 50, 100, 80, 6], ['concentre', 80, 600, 70, 4]] as [$type, $ms, $starch, $dt6, $quantity]) {
        $feed = new Aliment([
            'type' => $type, 'libelle0' => $type, 'ms' => $ms, 'amidon' => $starch, 'dt6_ami' => $dt6,
            'ufl' => 1, 'pdi' => 100, 'mo' => 900, 'd_mo' => 65, 'mat' => 150,
            'niref' => 2, 'lys_di' => 6.7, 'met_di' => 1.9, 'uel' => 1, 'prix' => 0,
        ]);
        $parameters = ['quantite' => $quantity * $scale * ($mode === 'MB' ? 100 / $ms : 1),
            'is_mb' => $mode === 'MB', 'is_volonte' => false];
        $items->push((new RationAliment($parameters))->setRelation('aliment', $feed));
        $recipe->push((new MelangeAliment($parameters))->setRelation('aliment', $feed));
    }
    $ration->setRelation('planRationnement', new PlanRationnement(['inra' => '2018']));
    $ration->setRelation('rationAliments', $mode === 'mélange' ? collect() : $items);
    $ration->setRelation('melanges', $mode === 'mélange'
        ? collect([(new Melange(['quantite' => 17 * $scale, 'is_mb' => true, 'is_volonte' => false]))->setRelation('melangeAliments', $recipe)])
        : collect());

    return $ration;
}

test('partitions starch between rumen intestine and faecal losses according to INRA 3.26 and 3.27', function (int $index, float $rumen, float $intestine, float $faeces) {
    $ration = starchDigestionRation();
    $feed = $ration->rationAliments[$index]->aliment;
    $value = new CalculValeur($ration, $feed);

    expect($value->calculerAmiD_ruAliment())->toEqualWithDelta($rumen, 1e-9);
    expect($value->calculerAmiD_intAliment())->toEqualWithDelta($intestine, 1e-9);
    expect($feed->amidon - $value->calculerAmiD_ruAliment() - $value->calculerAmiD_intAliment())
        ->toEqualWithDelta($faeces, 1e-9);
})->with([
    'forage DT=81.3984 percent' => [0, 81.3984, 15.3649216, 3.2366784],
    'concentrate DT=75.2294 percent' => [1, 451.3764, 122.7630936, 25.8605064],
]);

test('weights starch degradation by starch mass independently of ration size and representation', function (float $scale, string $mode) {
    $ration = starchDigestionRation($scale, $mode);

    expect(Apport::calculerNI($ration))->toEqualWithDelta(2.0, 1e-10);
    expect(Apport::calculerPCO($ration))->toEqualWithDelta(0.4, 1e-10);
    expect(Apport::calculerApportAmidon($ration))->toEqualWithDelta(3000 * $scale, 1e-8);
    expect(Apport::calculerApportAmiD_ru($ration))->toEqualWithDelta(229.3896, 1e-8);
    expect(Apport::calculerApportAmiD_int($ration))->toEqualWithDelta(58.3241904, 1e-8);
    expect(Apport::calculerDTAmi($ration))->toEqualWithDelta(76.4632, 1e-8);
})->with([0.5, 1.0, 2.0])->with(['MS', 'MB', 'mélange']);

test('returns zero starch flows for a mineral a starch free ration and an empty ration', function () {
    $ration = starchDigestionRation();
    $mineral = new CalculValeur($ration, new Aliment(['type' => 'mineral', 'amidon' => 100]));
    expect($mineral->calculerAmiD_ruAliment())->toBe(0.0);
    expect($mineral->calculerAmiD_intAliment())->toBe(0.0);
    foreach ($ration->rationAliments as $item) {
        $item->aliment->amidon = 0;
    }
    foreach ([false, true] as $empty) {
        if ($empty) {
            $ration->setRelation('rationAliments', collect());
        }
        expect(Apport::calculerApportAmiD_ru($ration))->toBe(0.0);
        expect(Apport::calculerApportAmiD_int($ration))->toBe(0.0);
        expect(Apport::calculerDTAmi($ration))->toBe(0.0);
    }
});

test('publishes the corrected ruminal starch and estimated pH', function () {
    $ration = starchDigestionRation();
    expect(Impact::calculerPH($ration))->toEqualWithDelta(6.1706104, 1e-9);
    $result = RationCalculator::calculer($ration);
    expect($result['indicateurs']['amid_ru'])->toBe(229.4);
    expect($result['indicateurs']['ph_ruminal'])->toBe(6.17);
});
