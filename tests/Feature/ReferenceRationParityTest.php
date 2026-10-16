<?php

declare(strict_types=1);

use App\Services\RationHelper;
use Tests\Support\ReferenceRations;

test('uses the confirmed animal characteristics and stage units', function () {
    $reports = ReferenceRations::reports();
    $dairy = ReferenceRations::ration($reports['laitiere'], 1);
    $beef = ReferenceRations::ration($reports['allaitante-blonde'], 0);

    expect($dairy->race)->toBe('prim_holstein');
    expect($dairy->poids_vif)->toBe(650);
    expect($dairy->pourcentage_primipare)->toBe(0);
    expect($dairy->age_mois)->toBe(50.0);
    expect($dairy->lait_objectif)->toBe(32.5);
    expect($dairy->tb_annuel)->toBe(40);
    expect($dairy->tp_annuel)->toBe(32);
    expect($dairy->poids_veau_naissance)->toBe(45);
    expect(RationHelper::calculerSemainesLactation($dairy))->toEqualWithDelta(25, 1e-10);
    expect(RationHelper::calculerSemainesGestation($dairy))->toEqualWithDelta(10, 1e-10);
    expect($beef->poids_vif)->toBe(680);
    expect($beef->race)->toBe('blonde');
    expect($beef->lait_objectif)->toBe(0.0);
})->group('reference-rations');

test('matches the reference composition for every reproducible result', function (string $reportId, int $scenario, bool $asMix) {
    $report = ReferenceRations::reports()[$reportId];
    $actual = ReferenceRations::calculate(ReferenceRations::ration($report, $scenario, $asMix));
    $differences = [];
    $checked = 0;

    foreach ($report['results'] as $row) {
        if (ReferenceRations::blockedReason($report, $row) !== null) {
            continue;
        }

        $label = $row['label'];
        $printed = $row['values'][$scenario];
        $expected = (float) $printed;
        $tolerance = ReferenceRations::tolerance($label, $printed);
        $this->assertArrayHasKey($label, $actual);
        $this->assertTrue(is_finite($actual[$label]), $label.' doit être fini.');
        $checked++;

        if (ReferenceRations::exceedsTolerance($actual[$label], $expected, $tolerance)) {
            $differences[] = sprintf('%s : référence=%s ; moteur=%.5f ; écart=%+.5f ; tolérance=%.5f',
                $label, $printed, $actual[$label], $actual[$label] - $expected, $tolerance);
        }
    }

    expect($checked)->toBeGreaterThan(0);
    $this->assertSame([], $differences, $report['source'].' / '.$report['scenarios'][$scenario]."\n".implode("\n", $differences));
})->with(ReferenceRations::scenarios())->with([
    'aliments directs' => false,
    'mêmes ingrédients en mélange' => true,
])->group('reference-rations');

test('reports reference results still unavailable after INRA completion', function (string $reportId, int $scenario) {
    $report = ReferenceRations::reports()[$reportId];
    $unverifiable = [];

    foreach ($report['results'] as $row) {
        if ($row['column'] !== 'value') {
            continue;
        }
        if (ReferenceRations::completedMetricKey($report, $row) !== null) {
            continue;
        }
        if (in_array($row['label'], ['Mois par rapport au vêlage', 'Lait estimé (kg/j)', 'Production de lait (kg/j)'], true)) {
            continue;
        }
        $reason = match (true) {
            $row['label'] === '(Amidon + Sucres (% MS))' => 'Sucres inconnus dans le rapport et le catalogue INRA fourni.',
            $report['mineral_percentages'] !== null && in_array($row['label'], ['Ca abs. Ruminants (g)', 'P abs. Ruminants (g)', 'Mg absorbable (g)', 'Prix (€)'], true)
                && $row['column'] === 'value' => 'Absorbabilités, potassium ou prix absents même des AMV de PostgreSQL.',
            $row['column'] !== 'value' => 'Contrainte/production correspondante sans équivalent exposé ou stade animal non confirmé.',
            default => 'Effet zootechnique ou résultat non exposé par notre moteur.',
        };
        $unverifiable[] = $row['label'].' / '.$row['column'].' : '.$reason;
    }

    if ($unverifiable !== []) {
        $this->markTestIncomplete($report['source'].' / '.$report['scenarios'][$scenario]."\n".implode("\n", $unverifiable));
    }

    $this->assertNotEmpty($report['results']);
})->with(ReferenceRations::scenarios())->group('reference-rations');
