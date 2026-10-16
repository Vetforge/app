<?php

declare(strict_types=1);

use App\Models\Aliment;
use Database\Seeders\AlimentSeeder;
use Tests\Support\InraFeedComplements;
use Tests\Support\RationComparisonReport;
use Tests\Support\ReferenceRations;

test('matches the frozen INRA profiles to the actual application seeder', function () {
    $this->seed(AlimentSeeder::class);

    foreach (InraFeedComplements::catalogue()['profiles'] as $profile) {
        if ($profile['source']['kind'] !== 'inra_seed_catalogue') {
            continue;
        }
        $aliment = Aliment::query()->where('code_inra', $profile['source']['code_inra'])->sole();
        foreach ($profile['attributes'] as $field => $value) {
            expect((float) $aliment->{$field}, $profile['source']['code_inra'].' / '.$field)
                ->toEqualWithDelta($value, 1e-9);
        }
    }
})->group('reference-rations');

test('compares Larelev results using documented INRA feed complements', function (string $reportId, int $scenario, bool $asMix) {
    $report = ReferenceRations::reports()[$reportId];
    $ration = ReferenceRations::ration($report, $scenario, $asMix, completeWithInra: true);
    $actual = ReferenceRations::calculate($ration);
    $rows = RationComparisonReport::compare($report, $scenario, $actual);
    $reportDirectory = getenv('RATION_COMPARISON_REPORT_DIR');
    if (is_string($reportDirectory) && $reportDirectory !== '') {
        RationComparisonReport::record($reportDirectory, $reportId, $scenario, $asMix, $rows);
    }
    $differences = [];
    $checked = 0;

    foreach ($rows as $row) {
        if ($row['status'] === 'Non comparé') {
            continue;
        }
        $checked++;
        if ($row['status'] === 'Erreur') {
            $differences[] = $row['reason'];
        } elseif ($row['status'] === 'Écart') {
            $differences[] = sprintf('%s / %s : référence=%s ; moteur=%.5f ; écart=%+.5f ; tolérance=%.5f',
                $row['label'], $row['column'], $row['reference'], $row['actual'], $row['delta'], $row['tolerance']);
        }
    }

    expect($checked)->toBeGreaterThan(15);
    $this->assertSame([], $differences, $report['source'].' / '.$report['scenarios'][$scenario]
        ."\nEntrées manquantes complétées par analogues INRA documentés ; les écarts ne prouvent pas à eux seuls une erreur d'équation.\n"
        .implode("\n", $differences));
})->with(ReferenceRations::scenarios())->with([
    'aliments directs' => false,
    'mêmes ingrédients en mélange' => true,
])->group('reference-rations', 'inra-completed');
