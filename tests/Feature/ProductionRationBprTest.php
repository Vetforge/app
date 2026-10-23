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
use App\Services\RationCalculator;

function productionRationBprSnapshot(bool $flattenMix = false): Ration
{
    $data = json_decode(file_get_contents(__DIR__.'/../Fixtures/production-ration-4-nutrition.json'), true, flags: JSON_THROW_ON_ERROR);
    $ration = new Ration($data['animal']);
    $ration->setRelation('planRationnement', new PlanRationnement(['inra' => $data['system']]));
    $items = collect();
    foreach ($data['ration_aliments'] as $item) {
        $items->push((new RationAliment([
            'quantite' => $item['quantite'], 'is_mb' => $item['is_mb'], 'is_volonte' => $item['is_volonte'],
        ]))->setRelation('aliment', new Aliment($item['aliment'])));
    }
    $mixes = collect();
    foreach ($data['melanges'] as $item) {
        $ingredients = collect();
        foreach ($item['melange_aliments'] as $ingredient) {
            $ingredients->push((new MelangeAliment([
                'quantite' => $ingredient['quantite'], 'is_mb' => $ingredient['is_mb'],
            ]))->setRelation('aliment', new Aliment($ingredient['aliment'])));
        }
        $mix = (new Melange([
            'quantite' => $item['quantite'], 'is_mb' => $item['is_mb'], 'is_volonte' => $item['is_volonte'],
        ]))->setRelation('melangeAliments', $ingredients);
        if ($flattenMix) {
            foreach ($ingredients as $ingredient) {
                $quantity = $mix->effectiveContributionForAliment($ingredient);
                $items->push((new RationAliment(['quantite' => $quantity['qty_ms'], 'is_mb' => false, 'is_volonte' => false]))
                    ->setRelation('aliment', $ingredient->aliment));
            }
        } else {
            $mixes->push($mix);
        }
    }
    $ration->setRelation('rationAliments', $items);
    $ration->setRelation('melanges', $mixes);

    return $ration;
}

test('preserves the analysed forage BPR when nitrogen DT6 was not provided', function (int $index, float $expected) {
    $ration = productionRationBprSnapshot();
    $feed = $ration->rationAliments[$index]->aliment;

    expect($feed->dt_n)->not->toBeNull();
    expect($feed->dt6_n)->toBeNull();
    expect((new CalculValeur($ration, $feed))->calculerBPRAliment())->toBe($expected);
    expect((new CalculValeur($ration, $feed))->calculerPDIAliment())->toBe(60.2);
})->with(['analysed hay' => [0, -40.8], 'analysed silage' => [1, 7.2]]);

test('keeps production ration BPR near equilibrium with incomplete analyses and complete concentrates', function (bool $flattenMix) {
    $ration = productionRationBprSnapshot($flattenMix);

    expect(Apport::calculerApportTotalMS($ration))->toEqualWithDelta(12.805337419525822, 1e-10);
    // Somme indépendante des contributions en kg MS : fourrages -40,8 / +7,2 / -55,
    // concentrés recalculés -56 / -15 / -22 / -25 / +135, divisée par 12,8053374195 kg MS, dont 0,1 kg MS de minéral à BPR nul.
    expect(Apport::calculerBPR($ration))->toEqualWithDelta(-0.274127953772194 * 12.705337419525822 / 12.805337419525822, 1e-9);
})->with(['original mix' => false, 'individual concentrate lines' => true]);

test('publishes the corrected BPR for the production ration', function () {
    $result = RationCalculator::calculer(productionRationBprSnapshot());
    expect($result['indicateurs']['bpr'])->toBe(-0.27);
});

test('uses tabulated BPR whenever the shared dynamic precursor contract is incomplete', function (string $missing) {
    $ration = productionRationBprSnapshot();
    $feed = clone $ration->melanges[0]->melangeAliments[1]->aliment;
    $feed->{$missing} = null;

    expect((new CalculValeur($ration, $feed))->calculerBPRAliment())->toBe(-13.0);
})->with(['mo', 'mat', 'd_mo', 'eb', 'dt6_n', 'dr_n']);

test('preserves negative positive zero and unknown tabulated BPR values without inventing nitrogen DT6', function (?float $tabulated) {
    $ration = productionRationBprSnapshot();
    $feed = clone $ration->rationAliments[0]->aliment;
    $feed->bpr = $tabulated;

    expect((new CalculValeur($ration, $feed))->calculerBPRAliment())->toBe($tabulated ?? 0.0);
})->with([-40.8, 7.2, 0.0, null]);

test('continues the dynamic BPR calculation for complete ingredients', function (int $index, float $expected) {
    $ration = productionRationBprSnapshot();
    $feed = $ration->melanges[0]->melangeAliments[$index]->aliment;

    expect((new CalculValeur($ration, $feed))->calculerBPRAliment())->toBe($expected);
})->with(['maize' => [0, -56.0], 'wheat' => [1, -15.0]]);

test('minerals have zero BPR even if an irrelevant tabulated value was entered', function () {
    $ration = productionRationBprSnapshot();
    $feed = new Aliment(['type' => 'mineral', 'bpr' => 100]);
    expect((new CalculValeur($ration, $feed))->calculerBPRAliment())->toBe(0.0);
});
