<?php

use App\Models\Aliment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;

it('opens a copy form when a user edits a system aliment', function () {
    $user = User::factory()->create();
    $aliment = Aliment::factory()->systemique()->create([
        'code_inra' => '0133',
        'libelle0' => 'Mais ensilage',
    ]);

    $this->actingAs($user)
        ->get(route('aliments.edit', $aliment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('aliments/Form')
            ->where('mode', 'copy')
            ->where('sourceAliment.id', $aliment->id)
            ->where('sourceAliment.code_inra', $aliment->code_inra)
            ->where('aliment.code_inra', null)
            ->where('aliment.libelle0', 'Copie de '.$aliment->libelle0)
        );
});

it('lets the owner edit their aliment', function () {
    $user = User::factory()->create();
    $aliment = Aliment::factory()->create([
        'user_id' => $user->id,
        'code_inra' => null,
    ]);

    $this->actingAs($user)
        ->get(route('aliments.edit', $aliment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('aliments/Form')
            ->where('mode', 'edit')
            ->where('aliment.id', $aliment->id)
            ->where('aliment.code_inra', null)
        );
});

it('lets an admin edit an inra aliment without duplicating it', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $aliment = Aliment::factory()->systemique()->create([
        'code_inra' => '0133',
        'libelle0' => 'Mais ensilage',
    ]);

    $this->actingAs($admin)
        ->get(route('aliments.edit', $aliment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('aliments/Form')
            ->where('mode', 'edit')
            ->where('aliment.id', $aliment->id)
            ->where('aliment.code_inra', $aliment->code_inra)
            ->missing('sourceAliment')
        );
});

it('searches aliments across libelle fields by words', function () {
    $user = User::factory()->create();
    $matchingAliment = Aliment::factory()->create([
        'user_id' => $user->id,
        'libelle0' => 'Foins',
        'libelle1' => 'Barriac',
        'type' => 'Fourrage',
    ]);
    Aliment::factory()->create([
        'user_id' => $user->id,
        'libelle0' => 'Foins',
        'libelle1' => 'Prairie',
        'type' => 'Fourrage',
    ]);
    Aliment::factory()->systemique()->create([
        'libelle0' => 'Maïs ensilage',
        'libelle1' => 'Table INRA',
    ]);

    $this->actingAs($user)
        ->get(route('aliments.index', ['search' => 'foin barriac', 'type' => '']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('aliments/Index')
            ->has('aliments.data', 1)
            ->where('aliments.data.0.id', $matchingAliment->id)
            ->where('filters.search', 'foin barriac')
        );
});

it('forbids opening another users aliment', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $aliment = Aliment::factory()->create([
        'user_id' => $owner->id,
        'code_inra' => null,
    ]);

    $this->actingAs($user)
        ->get(route('aliments.edit', $aliment))
        ->assertForbidden();
});

it('forbids copying another users aliment', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $aliment = Aliment::factory()->create([
        'user_id' => $owner->id,
        'code_inra' => null,
    ]);

    $this->actingAs($user)
        ->post(route('aliments.copy', $aliment))
        ->assertForbidden();
});

it('downloads the aliment pdf', function () {
    Pdf::fake();

    $user = User::factory()->create();
    $aliment = Aliment::factory()->create([
        'user_id' => $user->id,
        'libelle0' => 'Mais ensilage',
        'libelle1' => 'Maïs NIR ferme',
    ]);

    $this->actingAs($user)
        ->get(route('aliments.pdf', $aliment))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) use ($aliment) {
        expect($pdf->viewName)->toBe('pdf.aliment');
        expect($pdf->viewData['aliment']->is($aliment))->toBeTrue();
        expect($pdf->downloadName)->toContain('aliment-mais-ensilage');

        return true;
    });
});

it('forbids downloading another users aliment pdf', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $aliment = Aliment::factory()->create([
        'user_id' => $owner->id,
        'code_inra' => null,
    ]);

    $this->actingAs($user)
        ->get(route('aliments.pdf', $aliment))
        ->assertForbidden();
});

it('renders the aliment pdf with two 2018 pages and one 2007 page', function () {
    $aliment = Aliment::factory()->make([
        'libelle0' => 'Mais ensilage',
        'libelle1' => 'Maïs NIR ferme',
        'type' => 'Fourrage',
        'code_inra' => '0158',
        'ufl' => 0.945,
        'pdi' => 81.2,
        'uem' => 1.084,
        'ufl2007' => 0.921,
        'ufv2007' => 0.874,
        'pdie2007' => 79.4,
        'pdin2007' => 74.1,
    ]);

    $html = view('pdf.aliment', ['aliment' => $aliment])->render();

    expect($html)->toContain('Page 1/3 - Référentiel INRA 2018')
        ->toContain('Page 2/3 - Référentiel INRA 2018')
        ->toContain('Page 3/3 - Référentiel INRA 2007')
        ->toContain('Référentiel INRA 2007');
});
