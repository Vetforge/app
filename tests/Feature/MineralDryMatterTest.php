<?php

declare(strict_types=1);

use App\Models\Aliment;
use App\Models\Melange;
use App\Models\MelangeAliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Services\Equations2007\Apport as Apport2007;
use App\Services\Equations2018\Apport as Apport2018;
use App\Services\RationHelper;

it('normalizes mineral DM for legacy reads, serialization and subsequent saves', function (?float $storedMs) {
    $aliment = Aliment::factory()->create(['type' => 'Mineraux', 'ms' => $storedMs]);
    expect((float) $aliment->fresh()->getRawOriginal('ms'))->toBe(100.0);

    // Simulate an old record without triggering saving observers.
    Aliment::query()->whereKey($aliment->id)->update(['ms' => $storedMs]);
    $legacy = $aliment->fresh();
    expect($legacy->ms)->toBe(100.0)->and($legacy->toArray()['ms'])->toBe(100.0);
    $legacy->libelle0 = 'Minéral corrigé';
    $legacy->save();
    expect((float) $legacy->fresh()->getRawOriginal('ms'))->toBe(100.0);
})->with([null, 0.0, 50.0, 101.0]);

it('preserves nonmineral DM and does not keep a cached mineral value after changing type', function () {
    $aliment = new Aliment(['type' => 'fourrage', 'ms' => 85]);
    expect($aliment->ms)->toBe(85);
    $aliment->type = 'mineral';
    expect($aliment->ms)->toBe(100.0);
    $aliment->type = 'fourrage';
    expect($aliment->ms)->toBe(85);
});

it('counts mineral DM and minerals identically in MS or MB, directly or in a wet forage mixture', function (string $inra, bool $mixed, bool $mineralInMb, ?float $storedMs) {
    $apport = $inra === '2018' ? Apport2018::class : Apport2007::class;
    $ration = Ration::factory()->make(['plan_rationnement_id' => 1, 'categorie_animal' => 'vache_laitiere', 'poids_vif' => 650]);
    $ration->setRelation('planRationnement', new PlanRationnement(['inra' => $inra]));
    $forage = new Aliment(['type' => 'fourrage', 'ms' => 50, 'ca' => 0, 'p' => 0, 'prix' => 0]);
    $mineral = new Aliment(['type' => 'mineral', 'ms' => $storedMs, 'ca' => 300, 'p' => 100, 'prix' => 1]);
    $ration->setRelation('rationAliments', collect());
    $ration->setRelation('melanges', collect());

    if ($mixed) {
        $recipe = collect([
            (new MelangeAliment(['quantite' => 10, 'is_mb' => true]))->setRelation('aliment', $forage),
            (new MelangeAliment(['quantite' => 0.2, 'is_mb' => $mineralInMb]))->setRelation('aliment', $mineral),
        ]);
        $mix = (new Melange(['quantite' => 10.2, 'is_mb' => true]))->setRelation('melangeAliments', $recipe);
        $ration->setRelation('melanges', collect([$mix]));
        expect($mix->effectiveContributionForAliment($recipe[1]))->toBe(['qty_ms' => 0.2, 'qty_mb' => 0.2]);
        expect(RationHelper::calculerProportionFourrage($mix))->toEqualWithDelta(5 / 5.2, 1e-12);
        expect(RationHelper::calculerMoyennePondereeMelange($ration, $mix, 'ca'))->toEqualWithDelta(60 / 5.2, 1e-12);
        // The same ration must also work when the mixture quantity is entered as DM.
        $mix->is_mb = false;
        $mix->quantite = 5.2;
    } else {
        $ration->setRelation('rationAliments', collect([
            (new RationAliment(['quantite' => 10, 'is_mb' => true]))->setRelation('aliment', $forage),
            (new RationAliment(['quantite' => 0.2, 'is_mb' => $mineralInMb]))->setRelation('aliment', $mineral),
        ]));
    }

    foreach ([false, true] as $precomputed) {
        try {
            if ($precomputed) {
                $apport::precompute($ration);
            }
            expect($apport::calculerApportTotalMS($ration))->toEqualWithDelta(5.2, 1e-12)
                ->and($apport::calculerApportMSParMB($ration))->toEqualWithDelta(5.2 / 10.2, 1e-10)
                ->and($apport::calculerApportCa($ration))->toEqualWithDelta(60.0, 1e-10)
                ->and($apport::calculerApportP($ration))->toEqualWithDelta(20.0, 1e-10);
        } finally {
            $apport::clearCache();
        }
    }
})->with(['2018', '2007'])->with([false, true])->with([false, true])->with([null, 0.0, 50.0, 101.0]);
