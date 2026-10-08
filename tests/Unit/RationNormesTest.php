<?php

use App\Support\RationNormes;

it('exposes supported bounds independently of missing defaults', function () {
    $definitions = collect(RationNormes::editable())->keyBy('key');
    expect($definitions['bpr'])->toMatchArray(['default_min' => null, 'default_max' => null, 'supports_min' => true, 'supports_max' => true])
        ->and($definitions['ph_ruminal'])->toMatchArray(['default_min' => null, 'supports_min' => true, 'supports_max' => false]);
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
