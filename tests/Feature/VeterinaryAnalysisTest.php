<?php

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\User;
use App\Models\UserModuleSetting;
use App\Services\VeterinaryAnalysisCalculator;
use App\Support\VeterinaryModules;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;

function veterinaryAnalysisPayload(array $payload = []): array
{
    return array_replace_recursive([
        'breeder_id' => null,
        'animal_nom' => 'Vache 42',
        'sampled_at' => '2026-05-01',
        'analyzed_at' => '2026-05-02',
        'intervenant' => 'Dr Martin',
        'payload' => [
            'species' => 'Bovin',
            'sample_nature' => 'Feces',
            'sample_count' => 1,
            'options' => ['dictyocaules' => false, 'cryptosporidies' => false, 'comptage' => true],
            'samples' => [
                ['name' => 'Lot 1', 'results' => ['strongles_digestifs' => '2', 'coccidies' => '0']],
            ],
            'advice_preventive' => 'Surveillance',
            'advice_curative' => 'Traitement selon clinique',
        ],
    ], $payload);
}

it('lists only analyses owned by the authenticated user for a module', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    $analysis = Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'coproscopie-parasitaire',
    ]);
    Analysis::factory()->create(['module' => 'coproscopie-parasitaire']);

    $this->actingAs($user)
        ->get(route('analyses.index', ['module' => 'coproscopie-parasitaire']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('analyses/Index')
            ->where('module.slug', 'coproscopie-parasitaire')
            ->has('analyses.data', 1)
            ->where('analyses.data.0.id', $analysis->id)
            ->where('analyses.data.0.breeder.name', 'GAEC du Val')
        );
});

it('filters analyses by breeder animal and intervenant fields', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create([
        'user_id' => $user->id,
        'name' => 'GAEC du Val',
        'city' => 'Rodez',
        'herd_number' => 'FR12121212',
    ]);
    $matchingAnalysis = Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'coproscopie-parasitaire',
        'animal_nom' => 'Jasmine',
        'sampled_at' => '2026-05-01',
        'analyzed_at' => '2026-05-02',
        'intervenant' => 'Dr Martin',
    ]);

    Analysis::factory()->create([
        'user_id' => $user->id,
        'module' => 'coproscopie-parasitaire',
        'animal_nom' => 'Marguerite',
        'intervenant' => 'Dr Durand',
    ]);

    $this->actingAs($user)
        ->get(route('analyses.index', ['module' => 'coproscopie-parasitaire', 'search' => 'rodez jasmine 2026-05']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('analyses/Index')
            ->where('filters.search', 'rodez jasmine 2026-05')
            ->where('analyses.total', 1)
            ->has('analyses.data', 1)
            ->where('analyses.data.0.id', $matchingAnalysis->id)
            ->where('analyses.data.0.breeder.name', 'GAEC du Val')
        );
});

it('returns analyses with scroll pagination metadata', function () {
    $user = User::factory()->create();

    Analysis::factory()->count(26)->create([
        'user_id' => $user->id,
        'module' => 'coproscopie-parasitaire',
    ]);

    $response = $this->actingAs($user)
        ->get(route('analyses.index', ['module' => 'coproscopie-parasitaire']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('analyses/Index')
            ->where('analyses.total', 26)
            ->has('analyses.data', 25)
        );

    $page = $response->viewData('page');

    expect(data_get($page, 'scrollProps.analyses.currentPage'))->toBe(1)
        ->and(data_get($page, 'scrollProps.analyses.nextPage'))->toBe(2);
});

it('requires a breeder but stores the animal name as a free text field', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('analyses.store', ['module' => 'coproscopie-parasitaire']), veterinaryAnalysisPayload([
            'breeder_id' => null,
        ]))
        ->assertSessionHasErrors('breeder_id');

    $this->actingAs($user)
        ->post(route('analyses.store', ['module' => 'coproscopie-parasitaire']), veterinaryAnalysisPayload([
            'breeder_id' => $breeder->id,
            'animal_nom' => 'Genisse jaune',
        ]))
        ->assertRedirect();

    $analysis = Analysis::query()->where('user_id', $user->id)->firstOrFail();

    expect($analysis->animal_nom)->toBe('Genisse jaune')
        ->and($analysis->breeder_id)->toBe($breeder->id)
        ->and($analysis->getAttributes())->not->toHaveKey('animal_id');
});

it('rejects breeders owned by another user when creating analyses', function () {
    $user = User::factory()->create();
    $foreignBreeder = Breeder::factory()->create();

    $this->actingAs($user)
        ->post(route('analyses.store', ['module' => 'coproscopie-parasitaire']), veterinaryAnalysisPayload([
            'breeder_id' => $foreignBreeder->id,
        ]))
        ->assertSessionHasErrors('breeder_id');
});

it('stores a settings snapshot when an analysis is created', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $settings = VeterinaryModules::defaultSettings('diagnostic-bacteriologique');
    $settings['antibiotics'] = [
        ['code' => 'TST', 'label' => 'Test antibiotique', 'dose' => null, 'intermediate_min' => 12, 'sensitive_min' => 18, 'enabled' => true],
    ];

    UserModuleSetting::factory()->create([
        'user_id' => $user->id,
        'module' => 'diagnostic-bacteriologique',
        'settings' => $settings,
    ]);

    $this->actingAs($user)
        ->post(route('analyses.store', ['module' => 'diagnostic-bacteriologique']), veterinaryAnalysisPayload([
            'breeder_id' => $breeder->id,
            'payload' => [
                'sample_nature' => 'Lait',
                'sample_identification' => 'Quartier AD',
                'commemoratives' => 'Mammite',
                'germ_count' => 1,
                'germs' => [
                    ['family' => 'Escherichia coli', 'antibiotics' => ['TST' => 19]],
                ],
                'advice' => 'Adapter au troupeau',
            ],
        ]))
        ->assertRedirect();

    $analysis = Analysis::query()->where('user_id', $user->id)->firstOrFail();

    UserModuleSetting::query()
        ->where('user_id', $user->id)
        ->where('module', 'diagnostic-bacteriologique')
        ->update(['settings' => ['antibiotics' => []]]);

    expect(data_get($analysis->settings_snapshot, 'antibiotics.0.code'))->toBe('TST')
        ->and(data_get($analysis->results, 'interpreted_germs.0.antibiotics.0.interpretation'))->toBe('S');
});

it('cleans legacy html from existing bse analysis text', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);

    UserModuleSetting::query()->create([
        'user_id' => $user->id,
        'module' => 'bse-allaitant',
        'settings' => [
            'txt_tx_diarrhee_veaux_total_s' => 'Plan<br><strong>diarrhees</strong>',
        ],
    ]);

    $analysis = Analysis::query()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'bse-allaitant',
        'status' => 'complete',
        'payload' => ['commentaire' => 'Alerte <i>terrain</i>'],
        'settings_snapshot' => ['txt_tx_diarrhee_veaux_total_s' => 'Plan<br><strong>diarrhees</strong>'],
        'results' => [
            'commentaires' => [
                'tx_diarrhee_veaux_total' => [
                    's' => 'Plan<br><strong>diarrhees</strong>',
                    'ns' => '<span style="color: #FF4500">Suivi</span>',
                ],
            ],
        ],
    ]);

    $this->artisan('legacy:clean-analysis-text')
        ->assertSuccessful();

    $analysis->refresh();
    $setting = UserModuleSetting::query()->where('user_id', $user->id)->where('module', 'bse-allaitant')->firstOrFail();

    expect(data_get($analysis->payload, 'commentaire'))->toBe('Alerte terrain')
        ->and(data_get($analysis->settings_snapshot, 'txt_tx_diarrhee_veaux_total_s'))->toBe('Plan diarrhees')
        ->and(data_get($analysis->results, 'commentaires.tx_diarrhee_veaux_total.s'))->toBe('Plan diarrhees')
        ->and(data_get($analysis->results, 'commentaires.tx_diarrhee_veaux_total.ns'))->toBe('Suivi')
        ->and(data_get($setting->settings, 'txt_tx_diarrhee_veaux_total_s'))->toBe('Plan diarrhees');
});

it('filters coproscopy parasites by species and optional searches', function () {
    $settings = VeterinaryModules::defaultSettings('coproscopie-parasitaire');

    $results = VeterinaryAnalysisCalculator::calculate('coproscopie-parasitaire', [
        'species' => 'Equin',
        'options' => ['dictyocaules' => false, 'cryptosporidies' => false],
        'samples' => [
            [
                'name' => 'Lot 1',
                'results' => [
                    'strongles_digestifs' => '3',
                    'parascaris_equorum' => '2',
                    'dictyocaulus_arnfieldi' => '3',
                    'cryptosporidium_parvum' => '2',
                ],
            ],
        ],
    ], $settings);

    expect(data_get($results, 'positive_parasites'))
        ->toHaveKey('parascaris_equorum')
        ->not->toHaveKey('strongles_digestifs')
        ->not->toHaveKey('dictyocaulus_arnfieldi')
        ->not->toHaveKey('cryptosporidium_parvum');
});

it('filters neonatal diarrhea pathogens by legacy test and coccidiosis option', function () {
    $settings = VeterinaryModules::defaultSettings('diarrhee-neonatale');
    $legacySnapshotSettings = [
        ...$settings,
        'pathogens' => collect($settings['pathogens'])
            ->map(fn (array $pathogen): array => collect($pathogen)->except(['tests', 'requires_option'])->all())
            ->all(),
    ];
    $pathogens = [
        'rotavirus' => '2',
        'coronavirus' => '2',
        'ecoli_k99' => '2',
        'ecoli_cs31a' => '2',
        'clostridium_perfringens' => '2',
        'cryptosporidies' => '2',
        'giardia' => '2',
        'coccidies' => '2',
    ];

    $speedResults = VeterinaryAnalysisCalculator::calculate('diarrhee-neonatale', [
        'test_name' => 'Speed V-Diar',
        'coccidiosis_test' => false,
        'pathogens' => $pathogens,
    ], $legacySnapshotSettings);

    expect(data_get($speedResults, 'positives'))
        ->toHaveKey('rotavirus')
        ->toHaveKey('coronavirus')
        ->toHaveKey('ecoli_k99')
        ->toHaveKey('ecoli_cs31a')
        ->toHaveKey('cryptosporidies')
        ->not->toHaveKey('clostridium_perfringens')
        ->not->toHaveKey('giardia')
        ->not->toHaveKey('coccidies');

    $quickResults = VeterinaryAnalysisCalculator::calculate('diarrhee-neonatale', [
        'test_name' => 'Quick Diar 5',
        'coccidiosis_test' => true,
        'pathogens' => $pathogens,
    ], $settings);

    expect(data_get($quickResults, 'positives'))
        ->toHaveKey('rotavirus')
        ->toHaveKey('coronavirus')
        ->toHaveKey('ecoli_k99')
        ->toHaveKey('clostridium_perfringens')
        ->toHaveKey('cryptosporidies')
        ->toHaveKey('coccidies')
        ->not->toHaveKey('ecoli_cs31a')
        ->not->toHaveKey('giardia');
});

it('calculates blood gas deficits and perfusion rest values', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('analyses.store', ['module' => 'gaz-du-sang']), veterinaryAnalysisPayload([
            'breeder_id' => $breeder->id,
            'payload' => [
                'species' => 'Bovin',
                'weight' => 50,
                'enophtalmie' => 3,
                'dehydration' => null,
                'ph' => 7.2,
                'pco2' => 35,
                'hco3' => 15,
                'angap' => 20,
                'tco2' => 18,
                'na' => 135,
                'k' => 4.5,
                'cl' => 100,
                'glycemia' => 45,
                'perfusions' => ['bica_iso_1l' => 1],
            ],
        ]))
        ->assertRedirect();

    $results = Analysis::query()->where('user_id', $user->id)->firstOrFail()->results;

    expect(data_get($results, 'dehydration'))->toBe(5.5)
        ->and(data_get($results, 'species'))->toBe('Bovin')
        ->and(data_get($results, 'calculation_profile'))->toBe('ruminant')
        ->and(data_get($results, 'interpretations.ph.status'))->toBe('low')
        ->and(data_get($results, 'deficit_bicarbonate_g'))->toBe(33)
        ->and(data_get($results, 'apports.bicarbonate_g'))->toBe(14)
        ->and(data_get($results, 'restes.bicarbonate_g'))->toBe(19);
});

it('interprets bacteriology antibiograms with user thresholds', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $settings = VeterinaryModules::defaultSettings('diagnostic-bacteriologique');
    $settings['antibiotics'] = [
        ['code' => 'LOW', 'label' => 'Low', 'dose' => null, 'intermediate_min' => 12, 'sensitive_min' => 18, 'enabled' => true],
        ['code' => 'MID', 'label' => 'Mid', 'dose' => null, 'intermediate_min' => 12, 'sensitive_min' => 18, 'enabled' => true],
        ['code' => 'HIGH', 'label' => 'High', 'dose' => null, 'intermediate_min' => 12, 'sensitive_min' => 18, 'enabled' => true],
    ];

    UserModuleSetting::factory()->create([
        'user_id' => $user->id,
        'module' => 'diagnostic-bacteriologique',
        'settings' => $settings,
    ]);

    $this->actingAs($user)
        ->post(route('analyses.store', ['module' => 'diagnostic-bacteriologique']), veterinaryAnalysisPayload([
            'breeder_id' => $breeder->id,
            'payload' => [
                'sample_nature' => 'Ecouvillon',
                'commemoratives' => '',
                'germ_count' => 1,
                'germs' => [
                    ['family' => 'Autre', 'antibiotics' => ['LOW' => 10, 'MID' => 15, 'HIGH' => 20]],
                ],
            ],
        ]))
        ->assertRedirect();

    $rows = data_get(Analysis::query()->where('user_id', $user->id)->firstOrFail()->results, 'interpreted_germs.0.antibiotics');

    expect(collect($rows)->pluck('interpretation', 'code')->all())->toBe([
        'LOW' => 'R',
        'MID' => 'I',
        'HIGH' => 'S',
    ]);
});

it('keeps module settings isolated per user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('module-settings.update', ['module' => 'diarrhee-neonatale']), [
            'settings' => [
                'tests' => ['Test utilisateur'],
                'pathogens' => [
                    ['key' => 'rota', 'label' => 'Rota', 'enabled' => true],
                ],
            ],
        ])
        ->assertRedirect();

    expect(data_get(VeterinaryModules::settingsForUser($user, 'diarrhee-neonatale'), 'tests.0'))->toBe('Test utilisateur')
        ->and(data_get(VeterinaryModules::settingsForUser($otherUser, 'diarrhee-neonatale'), 'tests.0'))->not->toBe('Test utilisateur');
});

it('allows per-user tests rapides items to be created edited deleted and filtered by species', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('module-settings.update', ['module' => 'tests-rapides']), [
            'settings' => [
                'species_options' => ['Bovin', 'Chien'],
                'elisa_tests' => [
                    ['key' => 'elisa_bvd', 'label' => 'ELISA BVD modifie', 'species' => ['Bovin'], 'enabled' => true],
                ],
                'biochem_rapide' => [
                    ['key' => 'glycemie', 'label' => 'Glycemie terrain', 'unit' => 'g/L', 'species' => ['Chien'], 'enabled' => false],
                ],
                'pcr_tests' => [
                    ['key' => 'pcr_bvd', 'label' => 'PCR BVD', 'species' => ['Bovin'], 'enabled' => true],
                ],
                'optional_sections' => [
                    ['key' => 'bandelette_urinaire', 'label' => 'Bandelette', 'species' => ['Chien'], 'enabled' => true],
                    ['key' => 'frottis_sanguin', 'label' => 'Frottis', 'species' => ['Bovin'], 'enabled' => false],
                ],
            ],
        ])
        ->assertRedirect();

    $settings = VeterinaryModules::settingsForUser($user, 'tests-rapides');

    expect(collect($settings['elisa_tests'])->pluck('key')->all())->toBe(['elisa_bvd'])
        ->and(data_get($settings, 'elisa_tests.0.label'))->toBe('ELISA BVD modifie')
        ->and(data_get($settings, 'elisa_tests.0.species'))->toBe(['Bovin'])
        ->and(data_get($settings, 'biochem_rapide.0.enabled'))->toBeFalse()
        ->and(data_get($settings, 'pcr_tests.0.label'))->toBe('PCR BVD')
        ->and(data_get($settings, 'bandelette_urinaire'))->toBeTrue()
        ->and(data_get($settings, 'frottis_sanguin'))->toBeFalse()
        ->and(data_get(VeterinaryModules::normalizeSettings('tests-rapides', ['bandelette_urinaire' => false]), 'bandelette_urinaire'))->toBeFalse()
        ->and(data_get(VeterinaryModules::settingsForUser($otherUser, 'tests-rapides'), 'elisa_tests.0.key'))->not->toBe('elisa_bvd')
        ->and(VeterinaryModules::payloadTemplate('tests-rapides', $settings))->toHaveKey('pcr');

    $this->actingAs($user)
        ->post(route('analyses.store', ['module' => 'tests-rapides']), [
            'breeder_id' => $breeder->id,
            'animal_nom' => 'Veau 12',
            'sampled_at' => '2026-05-01',
            'analyzed_at' => '2026-05-02',
            'intervenant' => 'Dr Martin',
            'payload' => [
                'species' => 'Bovin',
                'sample_nature' => 'Sang',
                'identification' => 'Tube 1',
                'commemoratifs' => '',
                'elisa' => ['elisa_bvd' => 'pos'],
                'biochem_rapide' => [],
                'pcr' => ['pcr_bvd' => 'neg'],
                'bandelette' => [],
                'frottis' => [],
                'commentaires' => '',
            ],
        ])
        ->assertRedirect();

    $analysis = Analysis::query()
        ->where('user_id', $user->id)
        ->where('module', 'tests-rapides')
        ->firstOrFail();

    expect(data_get($analysis->settings_snapshot, 'pcr_tests.0.key'))->toBe('pcr_bvd')
        ->and(data_get($analysis->payload, 'pcr.pcr_bvd'))->toBe('neg');
});

it('allows per-user biochimie and hemogramme parameters to be created edited deleted and filtered by species', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('module-settings.update', ['module' => 'tests-biochimie']), [
            'settings' => [
                'species_options' => ['Bovin', 'Chien'],
                'params' => [
                    ['key' => 'MYBIO', 'label' => 'Bio perso', 'species' => ['Bovin'], 'enabled' => true],
                    ['key' => 'DOGBIO', 'label' => 'Bio chien', 'species' => ['Chien'], 'enabled' => false],
                ],
                'norms' => [
                    'Bovin' => [
                        'MYBIO' => ['min' => 1.2, 'max' => 3.4, 'unit' => 'u'],
                    ],
                    'Chien' => [
                        'DOGBIO' => ['min' => 5, 'max' => 8, 'unit' => 'du'],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route('module-settings.update', ['module' => 'hemogramme']), [
            'settings' => [
                'species_options' => ['Bovin'],
                'params' => [
                    ['key' => 'MYHGB', 'label' => 'Hemato perso', 'group' => 'leucocytes', 'species' => ['Bovin'], 'enabled' => true],
                ],
                'norms' => [
                    'Bovin' => [
                        'MYHGB' => ['min' => 10, 'max' => 20, 'unit' => 'G/L'],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $biochimieSettings = VeterinaryModules::settingsForUser($user, 'tests-biochimie');
    $hemogrammeSettings = VeterinaryModules::settingsForUser($user, 'hemogramme');

    expect(collect($biochimieSettings['params'])->pluck('key')->all())->toBe(['MYBIO', 'DOGBIO'])
        ->and(data_get($biochimieSettings, 'params.0.label'))->toBe('Bio perso')
        ->and(data_get($biochimieSettings, 'params.0.species'))->toBe(['Bovin'])
        ->and(data_get($biochimieSettings, 'params.1.enabled'))->toBeFalse()
        ->and(data_get($biochimieSettings, 'norms.Bovin.MYBIO.unit'))->toBe('u')
        ->and(data_get($biochimieSettings, 'norms.Chat'))->toBeNull()
        ->and(collect(VeterinaryModules::settingsForUser($otherUser, 'tests-biochimie')['params'])->pluck('key')->contains('MYBIO'))->toBeFalse()
        ->and(collect($hemogrammeSettings['params'])->pluck('key')->all())->toBe(['MYHGB'])
        ->and(data_get($hemogrammeSettings, 'params.0.group'))->toBe('leucocytes')
        ->and(data_get($hemogrammeSettings, 'norms.Bovin.MYHGB.max'))->toEqual(20)
        ->and(VeterinaryModules::normalizeSettings('tests-biochimie', ['species_options' => ['Bovin'], 'params' => []])['params'])->toBe([]);

    $this->actingAs($user)
        ->post(route('analyses.store', ['module' => 'tests-biochimie']), [
            'breeder_id' => $breeder->id,
            'animal_nom' => 'Vache 12',
            'sampled_at' => '2026-05-01',
            'analyzed_at' => '2026-05-02',
            'intervenant' => 'Dr Martin',
            'payload' => [
                'species' => 'Bovin',
                'sample_nature' => 'Serum',
                'identification' => 'Tube 1',
                'commemoratifs' => '',
                'params' => ['MYBIO' => '2.1'],
                'commentaires' => '',
            ],
        ])
        ->assertRedirect();

    $analysis = Analysis::query()
        ->where('user_id', $user->id)
        ->where('module', 'tests-biochimie')
        ->firstOrFail();

    expect(data_get($analysis->settings_snapshot, 'params.0.key'))->toBe('MYBIO')
        ->and(data_get($analysis->payload, 'params.MYBIO'))->toBe('2.1');
});

it('downloads an analysis pdf for the owner only', function () {
    Pdf::fake();

    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $analysis = Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'coproscopie-parasitaire',
    ]);

    $this->actingAs($user)
        ->get(route('analyses.pdf', $analysis))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) use ($analysis) {
        expect($pdf->viewName)->toBe('pdf.analysis');
        expect($pdf->viewData['analysis']->is($analysis))->toBeTrue();
        expect($pdf->downloadName)->toContain('analyse-'.$analysis->id);

        return true;
    });

    $this->actingAs(User::factory()->create())
        ->get(route('analyses.pdf', $analysis))
        ->assertNotFound();
});

it('renders result sections in pdf for every analysis module without a dedicated calculator', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);

    $cases = [
        'analyse-diverse' => [
            'settings' => VeterinaryModules::defaultSettings('analyse-diverse'),
            'payload' => [
                'species' => 'Bovin',
                'sample_count' => 1,
                'commemoratifs' => '<p>Commemoratif PDF</p>',
                'analyses' => [
                    ['type' => 'Ionogramme', 'results' => '<p>Resultat divers PDF</p>'],
                ],
                'commentaires' => 'Commentaire divers PDF',
            ],
            'expected' => ['Resultats d\'analyses', 'Ionogramme', 'Resultat divers PDF', 'Commentaire divers PDF'],
        ],
        'tests-rapides' => [
            'settings' => VeterinaryModules::normalizeSettings('tests-rapides', [
                'pcr_tests' => [
                    ['key' => 'pcr_bvd', 'label' => 'PCR BVD', 'species' => ['Bovin'], 'enabled' => true],
                ],
            ]),
            'payload' => [
                'species' => 'Bovin',
                'sample_nature' => 'Sang',
                'identification' => 'Tube 1',
                'commemoratifs' => '',
                'elisa' => ['bvd_ag' => 'pos'],
                'pcr' => ['pcr_bvd' => 'neg'],
                'biochem_rapide' => ['glycemie' => '0.8'],
                'bandelette' => ['ph' => '6'],
                'frottis' => ['babesia_bovis' => 'pos'],
                'commentaires' => 'RAS tests rapides PDF',
            ],
            'expected' => ['Tests ELISA', 'BVD Ag', 'Positif', 'PCR BVD', 'Negatif', 'Biochimie rapide', '0.8', 'Bandelette urinaire', 'Frottis sanguin'],
        ],
        'tests-biochimie' => [
            'settings' => VeterinaryModules::normalizeSettings('tests-biochimie', [
                'species_options' => ['Bovin'],
                'params' => [
                    ['key' => 'MYBIO', 'label' => 'Bio PDF', 'species' => ['Bovin'], 'enabled' => true],
                ],
                'norms' => [
                    'Bovin' => [
                        'MYBIO' => ['min' => 1, 'max' => 3, 'unit' => 'u'],
                    ],
                ],
            ]),
            'payload' => [
                'species' => 'Bovin',
                'sample_nature' => 'Serum',
                'identification' => 'Tube Bio',
                'commemoratifs' => '',
                'params' => ['MYBIO' => '4', 'UNKNOWN_BIO' => '9'],
                'commentaires' => 'Commentaire bio PDF',
            ],
            'expected' => ['Resultats biochimiques', 'Bio PDF', '4', 'Haut', 'UNKNOWN BIO', '9', 'Commentaire bio PDF'],
        ],
        'hemogramme' => [
            'settings' => VeterinaryModules::normalizeSettings('hemogramme', [
                'species_options' => ['Bovin'],
                'params' => [
                    ['key' => 'MYHGB', 'label' => 'Hemato PDF', 'group' => 'leucocytes', 'species' => ['Bovin'], 'enabled' => true],
                ],
                'norms' => [
                    'Bovin' => [
                        'MYHGB' => ['min' => 10, 'max' => 20, 'unit' => 'G/L'],
                    ],
                ],
            ]),
            'payload' => [
                'species' => 'Bovin',
                'sample_nature' => 'Sang EDTA',
                'identification' => 'Tube Hemato',
                'commemoratifs' => '',
                'params' => ['MYHGB' => '12', 'UNKNOWN_HEMATO' => '3'],
                'commentaires' => 'Commentaire hemato PDF',
            ],
            'expected' => ['Leucocytes', 'Hemato PDF', '12', 'G/L', 'OK', 'UNKNOWN HEMATO', '3', 'Commentaire hemato PDF'],
        ],
        'autopsie' => [
            'settings' => VeterinaryModules::defaultSettings('autopsie'),
            'payload' => [
                'identification' => 'Veau autopsie',
                'species' => 'Bovin',
                'sexe' => 'M',
                'conformation' => 'Bonne',
                'conservation' => 'Correcte',
                'engraissement' => 'Moyen',
                'poids' => 42,
                'commemoratifs' => 'Historique PDF',
                'lesions' => 'Lesions PDF',
                'conclusion' => 'Conclusion PDF',
            ],
            'expected' => ['Identification necropsique', 'Veau autopsie', '42 kg', 'Lesions PDF', 'Conclusion PDF'],
        ],
    ];

    foreach ($cases as $module => $case) {
        $analysis = Analysis::factory()->create([
            'user_id' => $user->id,
            'breeder_id' => $breeder->id,
            'module' => $module,
            'payload' => $case['payload'],
            'settings_snapshot' => $case['settings'],
        ]);

        $analysis->load('breeder');

        $html = view('pdf.analysis', [
            'analysis' => $analysis,
            'module' => VeterinaryModules::get($module),
        ])->render();

        foreach ($case['expected'] as $expected) {
            expect($html)->toContain($expected);
        }

        expect($html)->not->toContain('&lt;p&gt;')
            ->and($html)->not->toContain('Conseils preventifs');
    }
});

it('renders compte-rendu pages in the pdf and strips legacy html tags', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create([
        'user_id' => $user->id,
        'name' => 'GAEC DES SOURCES VIVES',
    ]);
    $analysis = Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'compte-rendu',
        'payload' => [
            'pages' => ['<p>BRIX VEAUX</p><p>5411:9</p><p>5410: 8</p>'],
            'nb_pages' => 1,
        ],
    ]);

    $analysis->load('breeder');

    $html = view('pdf.analysis', [
        'analysis' => $analysis,
        'module' => VeterinaryModules::get('compte-rendu'),
    ])->render();

    expect($html)->toContain('BRIX VEAUX')
        ->and($html)->toContain('5411:9')
        ->and($html)->toContain('5410: 8')
        ->and($html)->not->toContain('&lt;p&gt;')
        ->and($html)->not->toContain('Conseils preventifs');
});
