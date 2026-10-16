<?php

declare(strict_types=1);

use Tests\Support\ReferenceRations;

test('retains all four reports and their ten scenarios', function () {
    expect(ReferenceRations::reports())->toHaveCount(4);
    expect(ReferenceRations::scenarios())->toHaveCount(10);

    $sourceDirectory = dirname(__DIR__, 2).'/docs/exemples_ration';
    if (is_dir($sourceDirectory)) {
        $sourceNames = array_map(basename(...), glob($sourceDirectory.'/*.rtf'));
        expect(array_column(ReferenceRations::reports(), 'source'))->toEqualCanonicalizing($sourceNames);
    }
});

test('preserves and classifies every printed result and feed field', function (string $id, int $scenarios, int $feeds, int $rows, int $comparableRows) {
    $report = ReferenceRations::reports()[$id];
    expect($report['system'])->toBe('2018');
    expect($report['source_sha256'])->toMatch('/^[a-f0-9]{64}$/');
    expect($report['reference_software'])->toBe(['name' => 'Larelev', 'version' => '2018']);
    expect($report['source_header'])->not->toBeEmpty();
    expect($report['unresolved_animal_inputs'])->not->toBeEmpty();
    expect($report['scenario_count'])->toBe($scenarios);
    expect($report['feeds'])->toHaveCount($feeds);
    expect($report['results'])->toHaveCount($rows);
    expect(array_filter($report['results'], fn (array $row): bool => ReferenceRations::blockedReason($report, $row) === null))
        ->toHaveCount($comparableRows);

    $source = dirname(__DIR__, 2).'/docs/exemples_ration/'.$report['source'];
    if (is_file($source)) {
        expect(hash_file('sha256', $source))->toBe($report['source_sha256'], 'Le rapport source a changé : réextraire les données de référence.');
    }

    foreach ($report['feeds'] as $feed) {
        expect(ReferenceRations::attributes($feed)['ms'])->toBeGreaterThan(0)->toBeLessThanOrEqual(100);
    }
    foreach ([...$report['quantities'], ...$report['results']] as $row) {
        expect($row['values'])->toHaveCount($scenarios);
        foreach ($row['values'] as $value) {
            expect($value)->toBeString()->toBeNumeric();
        }
    }
    foreach ($report['results'] as $row) {
        $reason = ReferenceRations::blockedReason($report, $row);
        if ($reason === null) {
            expect($row['label'])->toBeIn(ReferenceRations::COMPOSITION);
        } else {
            expect($reason)->not->toBeEmpty();
        }
    }
})->with([
    ['genisse-allaitante', 1, 5, 46, 16],
    ['allaitante-limousine', 3, 5, 47, 16],
    ['allaitante-blonde', 3, 4, 47, 3],
    ['laitiere', 3, 8, 74, 3],
]);

test('converts feed values from fresh matter without scaling digestibility percentages', function () {
    $feed = ReferenceRations::reports()['genisse-allaitante']['feeds'][0];
    $attributes = ReferenceRations::attributes($feed);

    expect($attributes['ms'])->toEqualWithDelta(86.7, 1e-10);
    expect($attributes['mat'])->toEqualWithDelta(67.6 / 0.867, 1e-10);
    expect($attributes['ufl'])->toEqualWithDelta(0.623 / 0.867, 1e-10);
    expect($attributes['eb'])->toEqualWithDelta(3740 / 0.867, 1e-10);
    expect($attributes['d_mo'])->toBe(60.7);
    expect($attributes['sucres'])->toBeNull();
    expect($attributes)->not->toHaveKey('dr_n');
});

test('keeps dry matter analyses and mineral absorption zeroes as printed', function () {
    $feeds = ReferenceRations::reports()['genisse-allaitante']['feeds'];
    $silage = ReferenceRations::attributes($feeds[2]);
    $mineral = ReferenceRations::attributes($feeds[1]);

    expect($silage['ms'])->toBe(50.8);
    expect($silage['mat'])->toBe(114.0);
    expect($silage['ufl'])->toBe(0.730);
    expect($mineral['pabs'])->toBe(0.0);
});

test('preserves the print precision independently of the engine', function () {
    expect(ReferenceRations::roundingRadius('87.10'))->toEqualWithDelta(0.05, 1e-10);
    expect(ReferenceRations::roundingRadius('0.811'))->toEqualWithDelta(0.0005, 1e-10);
    expect(ReferenceRations::roundingRadius('633'))->toBe(0.5);
});
