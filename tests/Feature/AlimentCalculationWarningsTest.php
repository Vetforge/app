<?php

use App\Models\Aliment;
use App\Models\Melange;
use App\Models\MelangeAliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Models\User;
use App\Services\AlimentCalculationWarnings;
use Inertia\Testing\AssertableInertia as Assert;

function completeWarningTestAliment(array $overrides = []): Aliment
{
    return new Aliment(array_replace([
        'type' => 'fourrage', 'libelle0' => 'Foin test', 'ms' => 85,
        'mo' => 900, 'mat' => 140, 'd_mo' => 65, 'eb' => 4200,
        'dt6_n' => 70, 'dr_n' => 65, 'amidon' => 0, 'dt6_ami' => null,
        'ag' => 20, 'pf' => 0, 'niref' => 1.5, 'bpr' => 0,
        'ufl' => 0.8, 'ufv' => 0.7, 'pdi' => 80, 'uel' => 1,
        'ndf' => 480, 'adf' => 250, 'cb' => 260, 'lys_di' => 6.5, 'met_di' => 2,
        'ca' => 6, 'caabs' => 2.4, 'p' => 3, 'pabs' => 1.8, 'mg' => 1,
        'na' => 0, 'k' => 0, 'cl' => 0, 's' => 0, 'cu' => 0, 'zn' => 0,
        'mn' => 0, 'co' => 0, 'se' => 0, 'i' => 0, 'molybdene' => 0,
    ], $overrides));
}

function warningTestRation(string $inra = '2018'): Ration
{
    $ration = Ration::factory()->make(['plan_rationnement_id' => 1, 'categorie_animal' => 'vache_laitiere']);
    $ration->setRelation('planRationnement', new PlanRationnement(['inra' => $inra]));

    return $ration;
}

it('does not mistake known zero values or zero starch for missing precursors', function () {
    $aliment = completeWarningTestAliment(['mat' => 0, 'ag' => 0, 'pf' => 0, 'bpr' => 0, 'ca' => 0, 'caabs' => 0]);
    expect(AlimentCalculationWarnings::forAliment($aliment, warningTestRation()))->toBe([]);
});

it('warns about every missing dynamic precursor and explicitly describes the tabulated fallback', function (string $field) {
    $warnings = AlimentCalculationWarnings::forAliment(completeWarningTestAliment([$field => null]), warningTestRation());
    expect(implode(' ', $warnings))->toContain($field, 'Calcul dynamique INRA 2018 impossible', 'tabulés utilisés');
})->with(['mo', 'mat', 'd_mo', 'eb', 'dt6_n', 'dr_n']);

it('warns when no tabulated fallback exists', function () {
    $warnings = AlimentCalculationWarnings::forAliment(completeWarningTestAliment(['dt6_n' => null, 'bpr' => null]), warningTestRation());
    expect(implode(' ', $warnings))->toContain('Valeurs tabulées absentes (bpr)', 'comptée à zéro');
});

it('warns about approximations from missing optional precursors', function (string $field) {
    $warnings = AlimentCalculationWarnings::forAliment(completeWarningTestAliment([$field => null]), warningTestRation());
    expect(implode(' ', $warnings))->toContain($field, 'valeurs par défaut');
})->with(['ag', 'niref', 'bpr']);

it('does not warn for absent or zero starch, or for known starch degradability', function (?float $starch, ?float $dt6) {
    $aliment = completeWarningTestAliment(['amidon' => $starch, 'dt6_ami' => $dt6, 'dt_ami' => null]);
    expect(AlimentCalculationWarnings::forAliment($aliment, warningTestRation()))->toBe([]);
})->with([[null, null], [0.0, null], [150.0, 0.0], [150.0, 80.0]]);

it('warns when starch is present and the DT6 needed by the calculation is missing', function () {
    $aliment = completeWarningTestAliment(['amidon' => 150, 'dt6_ami' => null, 'dt_ami' => 80]);
    expect(implode(' ', AlimentCalculationWarnings::forAliment($aliment, warningTestRation())))->toContain('DT6 amidon manquant', 'PDI recalculés');
    $aliment->dt6_n = null;
    expect(implode(' ', AlimentCalculationWarnings::forAliment($aliment, warningTestRation())))->toContain('Les valeurs tabulées ne corrigent pas ces indicateurs');
});

it('does not require PF and NIref for concentrates', function () {
    $aliment = completeWarningTestAliment(['type' => 'concentre', 'pf' => null, 'niref' => null, 'amidon' => null]);
    expect(AlimentCalculationWarnings::forAliment($aliment, warningTestRation()))->toBe([]);
});

it('requires PF only for fermented forages', function (string $process, bool $expectedWarning) {
    $aliment = completeWarningTestAliment(['procede_technologique' => $process, 'pf' => null]);
    $warnings = AlimentCalculationWarnings::forAliment($aliment, warningTestRation());
    expect(str_contains(implode(' ', $warnings), 'pf manquant'))->toBe($expectedWarning);
})->with([['foin', false], ['vert', false], ['paille', false], ['deshydrate', false], ['autre', false], ['ensile', true]]);

it('uses 100 percent DM for minerals without requiring protein precursors', function (?float $ms) {
    $aliment = completeWarningTestAliment(['type' => 'mineral', 'ms' => $ms, 'dt6_n' => null]);
    expect($aliment->ms)->toBe(100.0)
        ->and(AlimentCalculationWarnings::forAliment($aliment, warningTestRation()))->toBe([]);
})->with([null, 0.0, 85.0, 101.0]);

it('still warns when nonmineral dry matter is missing or invalid', function (string $type, ?float $ms) {
    $warnings = AlimentCalculationWarnings::forAliment(completeWarningTestAliment(['type' => $type, 'ms' => $ms]), warningTestRation());
    expect(implode(' ', $warnings))->toContain('conversion MS/MB impossible');
})->with(['fourrage', 'concentre'])->with([null, 0.0, 101.0]);

it('uses only INRA 2007 requirements for a 2007 ration', function () {
    $aliment = completeWarningTestAliment(['ufl2007' => 0.8, 'pdie2007' => 80, 'pdin2007' => 80, 'uel2007' => 1, 'dt6_n' => null]);
    expect(AlimentCalculationWarnings::forAliment($aliment, warningTestRation('2007')))->toBe([]);
    $aliment->pdie2007 = null;
    expect(implode(' ', AlimentCalculationWarnings::forAliment($aliment, warningTestRation('2007'))))->toContain('pdie2007', 'apports incomplets');
});

it('identifies other missing nutrients without masking them as known zero values', function (string $field, string $message) {
    $warnings = AlimentCalculationWarnings::forAliment(completeWarningTestAliment([$field => null]), warningTestRation());
    expect(implode(' ', $warnings))->toContain($field, $message);
})->with([
    ['ndf', 'Fibres non renseignées'], ['adf', 'Fibres non renseignées'], ['cb', 'Fibres non renseignées'],
    ['lys_di', 'efficacité des PDI'], ['met_di', 'efficacité des PDI'],
    ['k', 'Minéraux non renseignés'], ['se', 'Minéraux non renseignés'],
]);

it('exposes nonblocking warnings on ration rows, ingredients and collapsed mixtures', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'categorie_animal' => 'vache_laitiere']);
    $aliment = completeWarningTestAliment(['dt6_n' => null]);
    $aliment->save();
    $row = RationAliment::create(['ration_id' => $ration->id, 'aliment_id' => $aliment->id, 'quantite' => 5, 'is_mb' => false, 'is_volonte' => false, 'ordre' => 1]);
    $mix = Melange::create(['ration_id' => $ration->id, 'quantite' => 2, 'is_mb' => false, 'is_volonte' => false]);
    MelangeAliment::create(['melange_id' => $mix->id, 'aliment_id' => $aliment->id, 'quantite' => 1, 'is_mb' => false, 'ordre' => 1]);

    $this->actingAs($user)->get(route('plans.rations.composition', [$plan, $ration]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('erreurs_types', [])
            ->has('resultats.apports')
            ->where('ration.ration_aliments.0.calculation_warnings.0', fn ($warning) => str_contains($warning, 'dt6_n'))
            ->where('ration.melanges.0.melange_aliments.0.calculation_warnings.0', fn ($warning) => str_contains($warning, 'tabulés utilisés'))
            ->where('ration.melanges.0.calculation_warnings.0', fn ($warning) => str_contains($warning, 'Foin test :'))
        );

    $this->put(route('plans.rations.aliments.update', [$plan, $ration, $row]), ['quantite' => 6, 'is_mb' => false, 'is_volonte' => false])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect((float) $row->fresh()->quantite)->toBe(6.0)
        ->and($aliment->fresh()->dt6_n)->toBeNull()
        ->and($aliment->fresh()->getAttributes())->not->toHaveKey('calculation_warnings');
});

it('does not warn for absent amino acids outside dairy cows', function () {
    $ration = warningTestRation();
    $ration->categorie_animal = 'vache_allaitante';
    $aliment = completeWarningTestAliment(['lys_di' => null, 'met_di' => null, 'ueb' => 1]);
    expect(AlimentCalculationWarnings::forAliment($aliment, $ration))->toBe([]);
});

it('ignores unused INRA 2007 minerals and uses the energy field consumed by this engine', function () {
    $ration = warningTestRation('2007');
    $ration->categorie_animal = 'bovin_engraissement';
    $aliment = completeWarningTestAliment([
        'ufl2007' => 0.8, 'pdie2007' => 80, 'pdin2007' => 80, 'uel2007' => 1,
        'ufv2007' => null, 'cl' => null, 's' => null, 'se' => null, 'molybdene' => null,
    ]);
    expect(AlimentCalculationWarnings::forAliment($aliment, $ration))->toBe([]);
});

it('does not warn when mineral absorbability follows a model convention or a known zero total', function (string $inra, string $type, float $total) {
    $aliment = completeWarningTestAliment([
        'type' => $type, 'ca' => $total, 'p' => $total, 'caabs' => null, 'pabs' => null,
        'ufl2007' => 0.8, 'pdie2007' => 80, 'pdin2007' => 80, 'uel2007' => 1,
    ]);
    expect(AlimentCalculationWarnings::forAliment($aliment, warningTestRation($inra)))->toBe([]);
})->with([
    ['2018', 'fourrage', 10.0], ['2018', 'concentre', 0.0], ['2018', 'mineral', 10.0],
    ['2007', 'fourrage', 0.0], ['2007', 'concentre', 0.0], ['2007', 'mineral', 10.0],
]);

it('warns when missing absorbability actually causes a positive mineral contribution to be lost', function (string $inra, string $type) {
    $aliment = completeWarningTestAliment([
        'type' => $type, 'caabs' => null, 'pabs' => null,
        'ufl2007' => 0.8, 'pdie2007' => 80, 'pdin2007' => 80, 'uel2007' => 1,
    ]);
    expect(implode(' ', AlimentCalculationWarnings::forAliment($aliment, warningTestRation($inra))))->toContain('pabs', 'Minéraux non renseignés');
})->with([['2018', 'concentre'], ['2007', 'fourrage'], ['2007', 'concentre']]);

it('does not annotate inactive ration rows, mixture recipes or zero quantity ingredients', function () {
    $ration = warningTestRation();
    $aliment = completeWarningTestAliment(['dt6_n' => null]);
    $direct = (new RationAliment(['quantite' => 0]))->setRelation('aliment', $aliment);
    $active = (new MelangeAliment(['quantite' => 1]))->setRelation('aliment', $aliment);
    $inactive = (new MelangeAliment(['quantite' => 0]))->setRelation('aliment', $aliment);
    $mix = (new Melange(['quantite' => 0]))->setRelation('melangeAliments', collect([$active, $inactive]));
    $ration->setRelation('rationAliments', collect([$direct]));
    $ration->setRelation('melanges', collect([$mix]));
    AlimentCalculationWarnings::annotate($ration);
    expect($direct->calculation_warnings)->toBe([])
        ->and($mix->calculation_warnings)->toBe([])
        ->and($active->calculation_warnings)->toBe([])->and($inactive->calculation_warnings)->toBe([]);
    $mix->quantite = 2;
    AlimentCalculationWarnings::annotate($ration);
    expect($active->calculation_warnings)->not->toBeEmpty()
        ->and($inactive->calculation_warnings)->toBe([]);
});
