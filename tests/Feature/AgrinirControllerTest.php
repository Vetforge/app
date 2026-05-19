<?php

use App\Models\Aliment;
use App\Models\User;
use App\Services\Agrinir\ForageCalculator;
use Inertia\Testing\AssertableInertia as Assert;

function agrinirParams(array $overrides = []): array
{
    return array_replace([
        'humidite' => 34.8,
        'proteine' => 8.9,
        'ndf' => 42.1,
        'adf' => 24.4,
        'cendres' => 4.7,
        'matiere_grasse' => 3.3,
        'amidon' => 29.5,
        'ca' => 0.34,
        'p' => 0.25,
        'mg' => 0.11,
    ], $overrides);
}

function agrinirPayload(array $overrides = []): array
{
    $type = $overrides['type'] ?? 'maisE';
    $params = agrinirParams($overrides['params'] ?? []);

    unset($overrides['params']);

    return array_replace_recursive([
        'nom' => 'Maïs NIR ferme',
        'type' => $type,
        'inra' => '2018',
        'params' => $params,
        'valeurs' => ForageCalculator::calculer2018($type, $params),
    ], $overrides);
}

function agrinirParamsForType(string $type): array
{
    return ForageCalculator::requiresAmidon($type)
        ? agrinirParams()
        : agrinirParams(['amidon' => null]);
}

function agrinirTypes(): array
{
    $types = [];

    foreach (['herbeG', 'herbePP', 'mais', 'legumineuse'] as $famille) {
        foreach (ForageCalculator::typesForFamille($famille) as $group) {
            foreach ($group['options'] as $option) {
                $types[$option['value']] = $option['value'];
            }
        }
    }

    return $types;
}

it('calculates agrinir 2018 values as json', function () {
    $user = User::factory()->create();
    $params = agrinirParams();

    $this->actingAs($user)
        ->postJson(route('agrinir.calculer'), [
            'type' => 'maisE',
            'inra' => '2018',
            'params' => $params,
        ])
        ->assertOk()
        ->assertJsonPath('resultats.ufl', ForageCalculator::calculer2018('maisE', $params)['ufl'])
        ->assertJsonPath('resultats.pdi', ForageCalculator::calculer2018('maisE', $params)['pdi'])
        ->assertJsonPath('resultats2007.ufl2007', ForageCalculator::calculer2007('maisE', $params)['ufl2007'])
        ->assertJsonPath('resultats2007.pdie2007', ForageCalculator::calculer2007('maisE', $params)['pdie2007']);
});

it('uses reference aliment minerals for agrinir calculations when omitted', function () {
    $user = User::factory()->create();
    $reference = Aliment::factory()->systemique()->create([
        'code_inra' => '0158',
        'ca' => 7.2,
        'p' => 3.4,
        'mg' => 2.1,
    ]);
    $params = agrinirParams([
        'ca' => 1.8,
        'p' => null,
        'mg' => null,
    ]);
    $effectiveParams = array_replace($params, [
        'p' => $reference->p,
        'mg' => $reference->mg,
    ]);

    $this->actingAs($user)
        ->postJson(route('agrinir.calculer'), [
            'type' => 'maisE',
            'inra' => '2018',
            'aliment_de_reference_id' => $reference->id,
            'params' => $params,
        ])
        ->assertOk()
        ->assertJsonPath('resultats.ca', ForageCalculator::calculer2018('maisE', $effectiveParams)['ca'])
        ->assertJsonPath('resultats.caabs', ForageCalculator::calculer2018('maisE', $effectiveParams)['caabs'])
        ->assertJsonPath('resultats.p', ForageCalculator::calculer2018('maisE', $effectiveParams)['p'])
        ->assertJsonPath('resultats.pabs', ForageCalculator::calculer2018('maisE', $effectiveParams)['pabs'])
        ->assertJsonPath('resultats.mg', ForageCalculator::calculer2018('maisE', $effectiveParams)['mg']);
});

it('requires amidon for agrinir maize and sorghum calculations', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('agrinir.calculer'), [
            'type' => 'maisE',
            'inra' => '2018',
            'params' => agrinirParams(['amidon' => null]),
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['params.amidon']);

    expect($response->json('errors'))
        ->toHaveKey('params.amidon')
        ->and($response->json('errors')['params.amidon'][0])
        ->toBe("L'amidon est obligatoire pour ce type de fourrage.");
});

it('calculates agrinir 2018 equations for every supported type', function (string $type) {
    $params = agrinirParamsForType($type);
    $resultats = ForageCalculator::calculer2018($type, $params);

    expect(array_keys($resultats))
        ->toEqual([
            'ms', 'mat', 'ndf', 'adf', 'mo', 'cb', 'eb', 'em', 'de', 'dmo',
            'niref', 'dt_n', 'dr_n', 'ufl', 'ufv', 'pdia', 'pdi', 'bpr',
            'uem', 'uel', 'ueb', 'ca', 'caabs', 'p', 'pabs', 'mg',
        ]);

    expect($resultats['ufl'])->toBeFloat();
    expect($resultats['ufv'])->toBeFloat();
    expect($resultats['pdi'])->toBeFloat();
})->with(agrinirTypes());

it('calculates agrinir 2007 equations for every supported type', function (string $type) {
    $params = agrinirParamsForType($type);
    $resultats = ForageCalculator::calculer2007($type, $params);

    expect(array_keys($resultats))
        ->toEqual([
            'ufl2007', 'ufv2007', 'pdia2007', 'pdie2007', 'pdin2007',
            'dmo2007', 'dma2007', 'uem2007', 'uel2007', 'ueb2007',
        ]);

    expect($resultats['ufl2007'])->toBeFloat();
    expect($resultats['pdie2007'])->toBeFloat();
})->with(agrinirTypes());

it('renders the inertia root with a csrf token meta tag', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('agrinir.show'))
        ->assertOk()
        ->assertSee('name="csrf-token"', false)
        ->assertSee('content="'.csrf_token().'"', false);
});

it('lists inra aliments as optional agrinir reference models', function () {
    $user = User::factory()->create();
    $reference = Aliment::factory()->systemique()->create([
        'code_inra' => '0158',
        'libelle0' => 'Mais ensilage',
        'libelle1' => 'Table INRA',
        'type' => 'Fourrage',
    ]);
    Aliment::factory()->create([
        'user_id' => $user->id,
        'code_inra' => null,
        'libelle0' => 'Aliment personnel',
    ]);

    $this->actingAs($user)
        ->get(route('agrinir.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('agrinir/Show')
            ->has('referenceAliments', 1)
            ->where('referenceAliments.0.id', $reference->id)
            ->where('referenceAliments.0.code_inra', $reference->code_inra)
            ->where('referenceAliments.0.label', 'Mais ensilage - Table INRA')
        );
});

it('creates an agrinir aliment from a reference aliment when one is selected', function () {
    $user = User::factory()->create();
    $reference = Aliment::factory()->systemique()->create([
        'code_inra' => '0158',
        'type' => 'Fourrage',
        'libelle0' => 'Mais ensilage',
        'libelle1' => 'Table INRA',
        'ca' => 7.2,
        'p' => 3.4,
        'mg' => 2.1,
        'na' => 1.9,
        'vit_a' => 4200,
        'ufl2007' => 0.935,
        'pdie2007' => 82.4,
    ]);

    $payload = agrinirPayload([
        'nom' => 'Maïs NIR ferme',
        'aliment_de_reference_id' => $reference->id,
        'params' => [
            'ca' => null,
            'p' => null,
            'mg' => null,
        ],
    ]);

    $this->actingAs($user)
        ->post(route('agrinir.sauvegarder'), $payload)
        ->assertRedirect(route('aliments.index'))
        ->assertSessionHas('success');

    $aliment = Aliment::query()->where('user_id', $user->id)->sole();
    $effectiveParams = array_replace($payload['params'], [
        'ca' => $reference->ca,
        'p' => $reference->p,
        'mg' => $reference->mg,
    ]);
    $resultats2018 = ForageCalculator::calculer2018($payload['type'], $effectiveParams);
    $resultats2007 = ForageCalculator::calculer2007($payload['type'], $effectiveParams);

    expect($aliment->code_inra)->toBeNull()
        ->and($aliment->libelle0)->toBe($reference->libelle0)
        ->and($aliment->libelle1)->toBe('Maïs NIR ferme')
        ->and((float) $aliment->na)->toBe((float) $reference->na)
        ->and((float) $aliment->vit_a)->toBe((float) $reference->vit_a)
        ->and(round((float) $aliment->ufl, 3))->toBe($resultats2018['ufl'])
        ->and(round((float) $aliment->d_mo, 1))->toBe($resultats2018['dmo'])
        ->and(round((float) $aliment->ca, 2))->toBe($resultats2018['ca'])
        ->and(round((float) $aliment->caabs, 2))->toBe($resultats2018['caabs'])
        ->and(round((float) $aliment->p, 2))->toBe($resultats2018['p'])
        ->and(round((float) $aliment->pabs, 2))->toBe($resultats2018['pabs'])
        ->and(round((float) $aliment->mg, 2))->toBe($resultats2018['mg'])
        ->and(round((float) $aliment->ufl2007, 3))->toBe($resultats2007['ufl2007'])
        ->and(round((float) $aliment->pdie2007, 1))->toBe($resultats2007['pdie2007']);
});

it('creates an agrinir aliment without a reference aliment when the model is omitted', function () {
    $user = User::factory()->create();
    $params = [
        'humidite' => 18.2,
        'proteine' => 13.5,
        'ndf' => 51.3,
        'adf' => 31.6,
        'cendres' => 6.8,
        'matiere_grasse' => 2.4,
        'amidon' => null,
        'ca' => 0.62,
        'p' => 0.31,
        'mg' => 0.19,
    ];

    $payload = agrinirPayload([
        'nom' => 'Foin prairie printemps',
        'type' => 'herbePPF1',
        'params' => $params,
        'valeurs' => ForageCalculator::calculer2018('herbePPF1', $params),
    ]);

    $this->actingAs($user)
        ->post(route('agrinir.sauvegarder'), $payload)
        ->assertRedirect(route('aliments.index'));

    $aliment = Aliment::query()->where('user_id', $user->id)->sole();

    expect($aliment->code_inra)->toBeNull()
        ->and($aliment->libelle0)->toBe('Foins')
        ->and($aliment->libelle1)->toBe('Foin prairie printemps')
        ->and($aliment->type)->toBe('Fourrage')
        ->and($aliment->na)->toBeNull()
        ->and(round((float) $aliment->pdi, 1))->toBe($payload['valeurs']['pdi'])
        ->and($aliment->ufl2007)->not->toBeNull()
        ->and($aliment->pdie2007)->not->toBeNull();
});
