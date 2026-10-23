<?php

use App\Enums\CategorieAnimal;
use App\Support\RationNormes;

it('exposes supported bounds independently of missing defaults', function () {
    $definitions = collect(RationNormes::editable())->keyBy('key');
    expect($definitions['bpr'])->toMatchArray(['default_min' => null, 'default_max' => null, 'supports_min' => true, 'supports_max' => true])
        ->and($definitions['ph_ruminal'])->toMatchArray(['default_min' => null, 'supports_min' => true, 'supports_max' => true]);
});

it('distinguishes a cleared bound from an omitted bound', function () {
    $cleared = RationNormes::storeableOverrides(['bil_ufl' => ['min' => null]]);
    expect($cleared)->toBe(['bil_ufl' => ['min' => null, 'max' => null]])
        ->and(RationNormes::active($cleared)['bil_ufl']['min'])->toBeNull()
        ->and(RationNormes::active(['bil_ufl' => []])['bil_ufl']['min'])->toEqual(0)
        ->and(RationNormes::storeableOverrides(['bil_ufl' => []]))->toBeNull();
});

it('returns to defaults without keeping spurious zero overrides', function () {
    expect(RationNormes::storeableOverrides(RationNormes::defaults()))->toBeNull();
});

it('preserves legacy preferences in all other categories when saving or resetting one profile', function () {
    $legacy = ['bpr' => ['min' => 1.25, 'max' => 4.75], 'bil_ufl' => ['min' => null, 'max' => null]];
    $active = RationNormes::active($legacy, CategorieAnimal::ChevreLaitiere);
    $active['bpr'] = ['min' => 2, 'max' => 5];
    $stored = RationNormes::storeForCategory($legacy, CategorieAnimal::ChevreLaitiere, $active);

    foreach (CategorieAnimal::cases() as $category) {
        expect(RationNormes::active($stored, $category)['bpr'])->toEqual(
            $category === CategorieAnimal::ChevreLaitiere ? ['min' => 2, 'max' => 5] : $legacy['bpr'],
        )->and(RationNormes::active($stored, $category)['bil_ufl']['min'])->toBeNull();
    }

    $reset = RationNormes::storeForCategory($stored, CategorieAnimal::ChevreLaitiere, RationNormes::defaults());
    expect(RationNormes::active($reset, CategorieAnimal::ChevreLaitiere))->toBe(RationNormes::defaults())
        ->and(RationNormes::active($reset, CategorieAnimal::BrebisLaitiere)['bpr'])->toBe($legacy['bpr']);
});

it('supports both optional bounds for every ration metric', function () {
    foreach (RationNormes::editable() as $definition) {
        expect($definition)->toMatchArray(['supports_min' => true, 'supports_max' => true]);
    }
});

it('applies both bounds to technical indicators and leaves cleared metrics neutral', function () {
    $normes = RationNormes::active([
        'ph_ruminal' => ['min' => 6, 'max' => 6.5],
        'cb_par_kg_ms' => ['min' => null, 'max' => 200],
        'adf_par_kg_ms' => ['min' => 180, 'max' => null],
        'eff_pdi' => ['min' => 50, 'max' => 70],
    ]);
    $statuses = RationNormes::statuses(['indicateurs' => ['ph_ruminal' => 6.6, 'cb_par_kg_ms' => 201, 'adf_par_kg_ms' => 180, 'eff_pdi' => 0.71, 'bpr' => 100]], $normes);

    expect($statuses)->toMatchArray(['ph_ruminal' => 'alert', 'cb_par_kg_ms' => 'alert', 'adf_par_kg_ms' => 'ok', 'eff_pdi' => 'alert', 'bpr' => 'neutral']);
});

it('uses personal coverage bounds for targets critical alerts balances and minerals', function () {
    $normes = RationNormes::active([
        'couverture_uf' => ['min' => 80, 'max' => 120],
        'alerte_couverture_uf' => ['min' => 70, 'max' => 130],
        'bilan_uf' => ['min' => 80, 'max' => 120],
        'couverture_caabs' => ['min' => 80, 'max' => 120],
        'couverture_lait' => ['min' => 80, 'max' => null],
        'alerte_couverture_lait' => ['min' => 70, 'max' => null],
    ]);
    $resultats = ['apports' => ['ufl' => 9, 'caabs' => 90], 'besoins' => ['uf_total' => 10, 'caabs' => 100], 'impacts' => ['production_lait_attendue' => 27]];

    expect(RationNormes::statuses($resultats, $normes, 30))->toMatchArray(['couverture_uf' => 'ok', 'bilan_uf' => 'ok', 'couverture_caabs' => 'ok', 'couverture_lait' => 'ok']);
    $resultats['apports']['ufl'] = 6;
    expect(RationNormes::statuses($resultats, $normes)['couverture_uf'])->toBe('alert');
    $resultats['apports']['ufl'] = 12.5;
    expect(RationNormes::statuses($resultats, $normes)['couverture_uf'])->toBe('watch');
});

it('does not interpret missing requirements or unvalidated minerals as covered', function () {
    $resultats = ['apports' => ['caabs' => 20, 'ufl' => 10], 'besoins' => ['caabs' => 20, 'uf_total' => 0], 'meta' => ['mineraux_valides' => false]];
    expect(RationNormes::statuses($resultats, RationNormes::defaults()))->toMatchArray(['couverture_caabs' => 'neutral', 'bilan_caabs' => 'neutral', 'couverture_uf' => 'neutral']);
});

it('preserves the default IRA scoring and applies personal scoring thresholds', function () {
    $values = ['be' => 225.0, 'amid_ru' => 225.0, 'pco_percent' => 45.0, 'ndf_fourrages' => 275.0, 'ndf_total' => 325.0];
    expect(RationNormes::ira($values))->toBe(1.0);
    $normes = RationNormes::defaults();
    foreach (['be', 'amid_ru', 'pco_percent', 'ndf_fourrages', 'ndf_total'] as $key) {
        $normes['ira_'.$key] = ['min' => null, 'max' => null];
    }
    expect(RationNormes::ira($values, $normes))->toBe(0.0);
    $normes['ira_amid_ru'] = ['min' => 100, 'max' => 200];
    expect(RationNormes::ira($values, $normes))->toBe(0.4);
});
