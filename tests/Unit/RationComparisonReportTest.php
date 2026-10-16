<?php

declare(strict_types=1);

use Tests\Support\RationComparisonReport;
use Tests\Support\ReferenceRations;

function comparisonReportFixture(): array
{
    return [
        'id' => 'laitiere', 'mineral_percentages' => null,
        'results' => [
            ['label' => 'MS totale (kg)', 'column' => 'value', 'values' => ['10.00']],
            ['label' => 'PDI (g)', 'column' => 'value', 'values' => ['1900']],
            ['label' => 'BPR (g/kg MS)', 'column' => 'value', 'values' => ['-2.00']],
            ['label' => 'Prix (€)', 'column' => 'value', 'values' => ['0.00']],
            ['label' => 'Production de lait (kg/j)', 'column' => 'value', 'values' => ['25.00']],
        ],
    ];
}

function comparisonReportTemporaryDirectory(): string
{
    $directory = sys_get_temp_dir().'/ration-comparison-'.bin2hex(random_bytes(8));
    mkdir($directory.'/cas', recursive: true);
    test()->reportDirectory = $directory;

    return $directory;
}

afterEach(function () {
    if (! isset($this->reportDirectory)) {
        return;
    }
    foreach ([...glob($this->reportDirectory.'/cas/*'), ...glob($this->reportDirectory.'/rapport.*')] as $path) {
        unlink($path);
    }
    rmdir($this->reportDirectory.'/cas');
    rmdir($this->reportDirectory);
});

test('reports signed differences, tolerances and percentages using the reference magnitude', function () {
    $rows = RationComparisonReport::compare(comparisonReportFixture(), 0, [
        'MS totale (kg)' => 10.01, 'PDI (g)' => 1868.7, 'BPR (g/kg MS)' => -1.0, 'Prix (€)' => 1.0,
    ]);

    expect(array_column($rows, 'status'))->toBe(['OK', 'OK', 'OK', 'Écart', 'Non comparé']);
    expect($rows[1]['reference'])->toBe('1900');
    expect($rows[1]['delta'])->toEqualWithDelta(-31.3, 1e-9);
    expect($rows[1]['percent'])->toEqualWithDelta(-1.647368421, 1e-9);
    expect($rows[1]['tolerance'])->toBe(57.0);
    expect($rows[2]['delta'])->toBe(1.0);
    expect($rows[2]['percent'])->toBe(50.0);
    expect($rows[2]['tolerance'])->toBe(3.0);
    expect($rows[3]['percent'])->toBeNull();
    expect($rows[4]['actual'])->toBeNull();
});

test('flags absent and nonfinite engine values instead of treating them as zeroes', function () {
    $rows = RationComparisonReport::compare(comparisonReportFixture(), 0, [
        'MS totale (kg)' => INF, 'PDI (g)' => NAN, 'Prix (€)' => 0.0,
    ]);

    expect(array_column($rows, 'status'))->toBe(['Erreur', 'Erreur', 'Erreur', 'OK', 'Non comparé']);
    expect($rows[0]['actual'])->toBeNull();
    expect($rows[2]['reason'])->toContain('BPR (g/kg MS)');
    expect(json_encode($rows, JSON_THROW_ON_ERROR))->toBeString();
});

test('accepts the inclusive three percent and three point boundaries', function (string $key, int $index, float $actual, string $status) {
    $rows = RationComparisonReport::compare(comparisonReportFixture(), 0, [$key => $actual]);
    expect($rows[$index]['status'])->toBe($status);
})->with([
    'upper three percent boundary' => ['MS totale (kg)', 0, 10.3, 'OK'],
    'lower three percent boundary' => ['MS totale (kg)', 0, 9.7, 'OK'],
    'above three percent' => ['MS totale (kg)', 0, 10.30001, 'Écart'],
    'below three percent' => ['MS totale (kg)', 0, 9.69999, 'Écart'],
    'upper BPR boundary crosses zero' => ['BPR (g/kg MS)', 2, 1.0, 'OK'],
    'lower BPR boundary' => ['BPR (g/kg MS)', 2, -5.0, 'OK'],
    'above BPR boundary' => ['BPR (g/kg MS)', 2, 1.00001, 'Écart'],
    'below BPR boundary' => ['BPR (g/kg MS)', 2, -5.00001, 'Écart'],
    'zero reference matches zero' => ['Prix (€)', 3, 0.0, 'OK'],
    'zero reference has zero tolerance' => ['Prix (€)', 3, 1e-12, 'Écart'],
]);

test('accepts the reported PDI example within three percent', function () {
    $report = comparisonReportFixture();
    $report['results'][1]['values'] = ['705'];
    $rows = RationComparisonReport::compare($report, 0, ['PDI (g)' => 709.8]);

    expect($rows[1]['status'])->toBe('OK');
    expect($rows[1]['tolerance'])->toEqualWithDelta(21.15, 1e-10);
});

test('omits subrows and preserves only the reported value', function (string $column) {
    $report = comparisonReportFixture();
    $constraint = ['label' => 'PDI (g)', 'column' => $column, 'values' => ['999999']];
    $report['results'][] = $constraint;
    $rows = RationComparisonReport::compare($report, 0, ['PDI (g)' => 1900.0]);

    expect($rows)->toHaveCount(5);
    expect(array_unique(array_column($rows, 'column')))->toBe(['value']);
    expect(ReferenceRations::completedMetricKey($report, $constraint))->toBeNull();
})->with(['Optimum', 'Optimum*', 'Minimum', 'Minimum*', 'Maximum', 'Maximum*', 'Production correspondante']);

test('generates tables for all ten rations and both modes even when comparisons fail', function () {
    $directory = comparisonReportTemporaryDirectory();
    $rows = RationComparisonReport::compare(comparisonReportFixture(), 0, [
        'MS totale (kg)' => 10.01, 'PDI (g)' => 1868.7, 'BPR (g/kg MS)' => 2.0, 'Prix (€)' => 0.0,
    ]);
    $rows[1]['label'] = 'PDI | <script>alert(1)</script>';
    foreach (ReferenceRations::scenarios() as [$reportId, $scenario]) {
        foreach ([false, true] as $asMix) {
            RationComparisonReport::record($directory.'/cas', $reportId, $scenario, $asMix, $rows);
        }
    }

    expect(RationComparisonReport::generate($directory, 1))->toBeTrue();
    $markdown = file_get_contents($directory.'/rapport.md');
    $html = file_get_contents($directory.'/rapport.html');
    expect(glob($directory.'/cas/*.json'))->toHaveCount(20);
    expect($markdown)->toContain('10 rations', 'Code de sortie Pest : 1', '-31,3', '-1,64737', '57', '±3 %', '±3 points');
    expect(substr_count($markdown, '### Aliments directs'))->toBe(10);
    expect(substr_count($markdown, '### Mélange'))->toBe(10);
    expect($html)->toContain('<table>', 'PDI | &lt;script&gt;alert(1)&lt;/script&gt;');
    expect($html)->not->toContain('<script>alert(1)</script>');
});

test('marks a report incomplete when a calculation did not produce an export', function () {
    $directory = comparisonReportTemporaryDirectory();

    expect(RationComparisonReport::generate($directory, 2))->toBeFalse();
    expect(file_get_contents($directory.'/rapport.md'))->toContain('Rapport incomplet', 'Calcul absent', '10 rations');
    expect(file_exists($directory.'/rapport.html'))->toBeTrue();
});
