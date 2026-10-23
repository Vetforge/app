<?php

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\User;
use App\Support\VeterinaryModules;

it('keeps BSE values below their plots beside the labels without changing comparisons or thresholds', function (string $module, int $width, int $height) {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $settings = VeterinaryModules::defaultSettings($module);
    $payload = array_replace(VeterinaryModules::payloadTemplate($module, $settings), [
        'nb_vaches_productrices' => 100, 'nb_vaches_reproductrices' => 100,
        'nb_veaux_nes_vivants' => 105, 'nb_veaux_nes' => 84,
        'nb_morts_0a7' => 20, 'nb_non_delivrances' => 5,
        'nb_mammites_locales' => 25, 'nb_sevres' => 74,
        'ivv' => 390, 'nb_ivia1' => 48, 'nb_iviaf' => 110,
    ]);
    $previous = Analysis::factory()->create([
        'user_id' => $user->id, 'breeder_id' => $breeder->id, 'module' => $module,
        'settings_snapshot' => $settings, 'payload' => array_replace($payload, ['ivv' => 420]),
    ]);
    $analysis = Analysis::factory()->create([
        'user_id' => $user->id, 'breeder_id' => $breeder->id, 'module' => $module,
        'settings_snapshot' => $settings,
        'payload' => [...$payload, 'comparison_analysis_id' => $previous->id],
    ]);
    $this->actingAs($user);
    $page = visit(route('analyses.show', $analysis, absolute: false))->resize($width, $height)
        ->assertPresent('figure[class] figcaption')->assertNoJavaScriptErrors();

    $layout = $page->script(<<<'JS'
        () => {
            const figures = [...document.querySelectorAll('figure')].filter(figure => figure.querySelector('svg[role="img"]'));
            const ivv = figures.find(figure => figure.querySelector('figcaption > p:nth-child(2)').textContent.trim() === 'IVV');
            return {
                count: figures.length,
                valuesBelowPlots: figures.every(figure => figure.querySelector('figcaption > p').getBoundingClientRect().top >= figure.querySelector('svg').getBoundingClientRect().bottom),
                labelsBelowValues: figures.every(figure => figure.querySelector('figcaption > p:nth-child(2)').getBoundingClientRect().top >= figure.querySelector('figcaption > p').getBoundingClientRect().bottom),
                noFloatingValues: figures.every(figure => !figure.querySelector('svg text[font-weight]')),
                zeroVisible: figures.some(figure => figure.querySelector('figcaption > p').textContent.trim() === '0.0 %'),
                ivvValue: ivv.querySelector('figcaption > p').textContent.trim(),
                ivvComparison: ivv.querySelector('figcaption > p:last-child').textContent.trim(),
                ivvAccessible: ivv.querySelector('svg').getAttribute('aria-label'),
                ivvThresholds: [...ivv.querySelectorAll('svg text')].map(text => text.textContent.trim()),
                thresholdLabelsSeparated: figures.every(figure => {
                    const boxes = [...figure.querySelectorAll('svg text')].map(text => text.getBoundingClientRect()).sort((a, b) => a.top - b.top);
                    return boxes.every((box, index) => index === 0 || box.top >= boxes[index - 1].bottom);
                }),
                ivvMarker: Boolean(ivv.querySelector('svg circle')),
                noOverflow: document.documentElement.scrollWidth <= innerWidth,
            };
        }
    JS);
    expect($layout)->toMatchArray([
        'valuesBelowPlots' => true, 'labelsBelowValues' => true, 'noFloatingValues' => true,
        'zeroVisible' => true, 'ivvValue' => '390 j', 'ivvComparison' => 'Ancien: 420 j', 'thresholdLabelsSeparated' => true,
        'ivvAccessible' => 'IVV : 390 j, Ancien : 420 j', 'ivvMarker' => true, 'noOverflow' => true,
        'ivvThresholds' => $module === 'bse-laitier' ? ['400j', '420j'] : ['365j', '390j'],
    ])->and($layout['count'])->toBeGreaterThan(10);

    if (getenv('BSE_METRICS_CAPTURE')) {
        $page->script(<<<'JS'
            () => {
                const section = [...document.querySelectorAll('section')].find(section => section.querySelector('h2')?.textContent.includes('Impact de la reproduction'));
                section.scrollIntoView({ block: 'start' });
            }
        JS);
        $page->screenshot(false, $module.'-metrics-'.$width.'.png');
    }
})->with([
    'laitier desktop' => ['bse-laitier', 1440, 1100],
    'laitier mobile' => ['bse-laitier', 390, 844],
    'allaitant desktop' => ['bse-allaitant', 1440, 1100],
    'allaitant mobile' => ['bse-allaitant', 390, 844],
]);

it('distinguishes missing BSE values from zero without drawing a misleading bar', function (string $module) {
    $user = User::factory()->create();
    $settings = VeterinaryModules::defaultSettings($module);
    $analysis = Analysis::factory()->create([
        'user_id' => $user->id, 'module' => $module, 'settings_snapshot' => $settings,
        'payload' => VeterinaryModules::payloadTemplate($module, $settings),
    ]);
    $this->actingAs($user);
    $page = visit(route('analyses.show', $analysis, absolute: false))->assertNoJavaScriptErrors();
    expect($page->script(<<<'JS'
        () => {
            const figure = [...document.querySelectorAll('figure')].find(figure => figure.querySelector('figcaption > p:nth-child(2)')?.textContent.trim() === 'IVV');
            return {
                value: figure.querySelector('figcaption > p').textContent.trim(),
                bar: Boolean(figure.querySelector('svg rect')),
                accessible: figure.querySelector('svg').getAttribute('aria-label'),
            };
        }
    JS))->toBe(['value' => '–', 'bar' => false, 'accessible' => 'IVV : –']);
})->with(['bse-laitier', 'bse-allaitant']);
