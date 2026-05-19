<?php

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\User;
use App\Support\VeterinaryModules;

function diarrheeNeonataleAnalysis(User $user, Breeder $breeder, array $attributes = []): Analysis
{
    $settings = VeterinaryModules::defaultSettings('diarrhee-neonatale');

    return Analysis::factory()->create(array_merge([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'diarrhee-neonatale',
        'animal_nom' => 'Jasmine',
        'intervenant' => 'Dr Martin',
        'payload' => VeterinaryModules::payloadTemplate('diarrhee-neonatale', $settings),
        'settings_snapshot' => $settings,
    ], $attributes));
}

// ─────────────────────────────────────────────
// Smoke testing
// ─────────────────────────────────────────────

it('loads all diarrhee neonatale pages without JavaScript errors', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $analysis = diarrheeNeonataleAnalysis($user, $breeder);

    $this->actingAs($user);

    visit([
        '/analyses/diarrhee-neonatale',
        '/analyses/diarrhee-neonatale/create',
        '/analyses/'.$analysis->id,
        '/analyses/'.$analysis->id.'/edit',
    ])->assertNoSmoke();
});

// ─────────────────────────────────────────────
// Index
// ─────────────────────────────────────────────

it('displays the diarrhee neonatale list with module title and create button', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    diarrheeNeonataleAnalysis($user, $breeder, ['animal_nom' => 'Jasmine', 'intervenant' => 'Dr Martin']);

    $this->actingAs($user);

    visit('/analyses/diarrhee-neonatale')
        ->assertSee('Diarrhee neonatale')
        ->assertSee('Nouvelle analyse')
        ->assertSee('GAEC du Val')
        ->assertSee('Jasmine')
        ->assertSee('Dr Martin')
        ->assertNoJavaScriptErrors();
});

it('does not show diarrhee neonatale analyses from other users', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherBreeder = Breeder::factory()->create(['user_id' => $otherUser->id]);
    diarrheeNeonataleAnalysis($otherUser, $otherBreeder, ['animal_nom' => 'Milka']);

    $this->actingAs($user);

    visit('/analyses/diarrhee-neonatale')
        ->assertSee('Diarrhee neonatale')
        ->assertDontSee('Milka')
        ->assertNoJavaScriptErrors();
});

// ─────────────────────────────────────────────
// Create
// ─────────────────────────────────────────────

it('creates a new diarrhee neonatale analysis through the browser form', function () {
    $user = User::factory()->create();
    Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);

    $this->actingAs($user);

    visit('/analyses/diarrhee-neonatale/create')
        ->assertSee('Nouvelle analyse')
        ->assertSee('Diarrhee neonatale')
        ->fill('breeder_id', 'GAEC')
        ->click('GAEC du Val')
        ->fill('animal_nom', 'Marguerite')
        ->fill('analyzed_at', '2026-05-18')
        ->fill('intervenant', 'Dr Coutrot')
        ->fill('advice_preventive', 'Vacciner le troupeau')
        ->fill('advice_curative', 'Rehyratation orale et antibiotherapie')
        ->click("Enregistrer l'analyse")
        ->assertSee('Marguerite')
        ->assertSee('Dr Coutrot')
        ->assertNoJavaScriptErrors();
});

it('shows a validation error when no breeder is selected on diarrhee form', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    visit('/analyses/diarrhee-neonatale/create')
        ->fill('animal_nom', 'Marguerite')
        ->fill('analyzed_at', '2026-05-18')
        ->click("Enregistrer l'analyse")
        ->assertSee('Eleveur')
        ->assertNoJavaScriptErrors();
});

// ─────────────────────────────────────────────
// Show
// ─────────────────────────────────────────────

it('displays a diarrhee neonatale analysis with breeder, animal and edit button', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    $analysis = diarrheeNeonataleAnalysis($user, $breeder, [
        'animal_nom' => 'Jasmine',
        'intervenant' => 'Dr Martin',
    ]);

    $this->actingAs($user);

    visit('/analyses/'.$analysis->id)
        ->assertSee('Diarrhee neonatale')
        ->assertSee('GAEC du Val')
        ->assertSee('Jasmine')
        ->assertSee('Dr Martin')
        ->assertSee('Modifier')
        ->assertNoJavaScriptErrors();
});

it('returns 404 when viewing a diarrhee neonatale analysis owned by another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherBreeder = Breeder::factory()->create(['user_id' => $otherUser->id]);
    $analysis = diarrheeNeonataleAnalysis($otherUser, $otherBreeder);

    $this->actingAs($user);

    $this->get('/analyses/'.$analysis->id)->assertNotFound();
});

// ─────────────────────────────────────────────
// Edit
// ─────────────────────────────────────────────

it('edits a diarrhee neonatale analysis and persists the updated fields', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    $analysis = diarrheeNeonataleAnalysis($user, $breeder, [
        'animal_nom' => 'Jasmine',
        'intervenant' => 'Dr Martin',
    ]);

    $this->actingAs($user);

    visit('/analyses/'.$analysis->id.'/edit')
        ->assertSee('Modifier analyse')
        ->fill('animal_nom', 'Marguerite')
        ->fill('intervenant', 'Dr Coutrot')
        ->fill('advice_curative', 'Traitement mis a jour')
        ->click("Enregistrer l'analyse")
        ->assertSee('Marguerite')
        ->assertSee('Dr Coutrot')
        ->assertNoJavaScriptErrors();
});

it('navigates back to the show page when cancelling diarrhee neonatale edit', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $analysis = diarrheeNeonataleAnalysis($user, $breeder);

    $this->actingAs($user);

    visit('/analyses/'.$analysis->id.'/edit')
        ->assertSee('Modifier analyse')
        ->click('Annuler')
        ->assertSee('Analyse #'.$analysis->id)
        ->assertNoJavaScriptErrors();
});

// ─────────────────────────────────────────────
// Delete
// ─────────────────────────────────────────────

it('deletes a diarrhee neonatale analysis after confirming the dialog', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    diarrheeNeonataleAnalysis($user, $breeder, ['animal_nom' => 'Jasmine']);

    $this->actingAs($user);

    $page = visit('/analyses/diarrhee-neonatale');
    $page->assertSee('Jasmine');

    $page->script('() => { window.confirm = () => true; return true; }');
    $page->script('() => { document.querySelector("[title=\'Supprimer\']").click(); return true; }');
    $page->waitForEvent('networkidle');

    $page->assertDontSee('Jasmine')
        ->assertSee('Diarrhee neonatale')
        ->assertNoJavaScriptErrors();
});

it('keeps the analysis when the user cancels the diarrhee neonatale deletion dialog', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    diarrheeNeonataleAnalysis($user, $breeder, ['animal_nom' => 'Jasmine']);

    $this->actingAs($user);

    $page = visit('/analyses/diarrhee-neonatale');
    $page->assertSee('Jasmine');

    $page->script('() => { window.confirm = () => false; return true; }');
    $page->script('() => { document.querySelector("[title=\'Supprimer\']").click(); return true; }');
    $page->waitForEvent('networkidle');

    $page->assertSee('Jasmine')
        ->assertNoJavaScriptErrors();
});
