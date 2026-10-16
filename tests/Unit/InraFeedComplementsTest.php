<?php

declare(strict_types=1);

use Tests\Support\InraFeedComplements;
use Tests\Support\ReferenceRations;

test('freezes identifiable INRA and database profiles with their provenance', function () {
    $catalogue = InraFeedComplements::catalogue();
    expect($catalogue['profiles'])->toHaveCount(16);
    expect($catalogue['matches'])->toHaveCount(13);
    foreach ($catalogue['profiles'] as $code => $profile) {
        expect($profile['label'])->not->toBeEmpty();
        expect($profile['attributes']['ms'])->toBeGreaterThan(0)->toBeLessThanOrEqual(100);
        if ($profile['source']['kind'] === 'inra_seed_catalogue') {
            expect($profile['source']['code_inra'])->toBe($code);
            $file = dirname(__DIR__, 2).'/'.$profile['source']['file'];
            expect(hash_file('sha256', $file))->toBe($profile['source']['sha256']);
        } else {
            expect($profile['source']['kind'])->toBe('postgres_snapshot');
            expect($profile['source']['table'])->toBe('aliments');
        }
    }
});

test('completes precursors while keeping every printed value including zeroes', function () {
    foreach (ReferenceRations::reports() as $report) {
        foreach ($report['feeds'] as $feed) {
            $original = ReferenceRations::attributes($feed);
            $completed = InraFeedComplements::attributes($feed);
            foreach ($original as $field => $value) {
                if ($value !== null) {
                    expect($completed[$field], $feed['name'].' / '.$field)->toBe($value);
                }
            }
        }
    }

    $hay = ReferenceRations::reports()['genisse-allaitante']['feeds'][0];
    $completedHay = InraFeedComplements::attributes($hay);
    expect($completedHay['dr_n'])->toBe(67.0);
    expect($completedHay['ag'])->toBe(14.0);
    expect($completedHay['eb'])->toEqualWithDelta(3740 / 0.867, 1e-9);
    expect($completedHay['amidon'])->toBe(0.0);
    expect($completedHay['sucres'] ?? null)->toBeNull();
    expect($completedHay['pf'] ?? null)->toBeNull();
});

test('derives mixed concentrate complements from the printed fresh matter recipe', function () {
    $feed = ReferenceRations::reports()['genisse-allaitante']['feeds'][3];
    $attributes = InraFeedComplements::attributes($feed);

    expect($attributes['ms'])->toBe(87.8);
    expect($attributes['mat'])->toEqualWithDelta(166 / 0.878, 1e-9);
    expect($attributes['dr_n'])->toBeGreaterThan(79)->toBeLessThan(95);
    expect($attributes['ag'])->toBeGreaterThan(0);
    expect($attributes['eb'])->toEqualWithDelta(4100 / 0.878, 1e-9);
    expect(InraFeedComplements::catalogue()['matches'][$feed['name']]['recipe'])->toBe([
        'CC0150' => 15.7, 'CC0020' => 19.9, 'CC0010' => 15.7, 'CC0130' => 15.7, 'CX0210' => 33,
    ]);
});

test('uses the closest database mineral without overwriting the reported mineral percentages', function () {
    $attributes = InraFeedComplements::automaticMineral([0, 27, 8]);
    expect($attributes['ms'])->toBe(100);
    expect($attributes['mat'])->toBe(0);
    expect($attributes['p'])->toBe(0.0);
    expect($attributes['ca'])->toBe(270.0);
    expect($attributes['mg'])->toBe(80.0);
    expect($attributes)->not->toHaveKey('caabs');
    expect($attributes)->not->toHaveKey('k');
});
