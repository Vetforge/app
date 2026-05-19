<?php

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\User;
use App\Support\VeterinaryModules;

function diagnosticBacteriologiqueAnalysis(User $user, Breeder $breeder, array $attributes = []): Analysis
{
    $settings = VeterinaryModules::defaultSettings('diagnostic-bacteriologique');

    return Analysis::factory()->create(array_merge([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'diagnostic-bacteriologique',
        'animal_nom' => 'Jasmine',
        'intervenant' => 'Dr Martin',
        'payload' => VeterinaryModules::payloadTemplate('diagnostic-bacteriologique', $settings),
        'settings_snapshot' => $settings,
    ], $attributes));
}

// ─────────────────────────────────────────────
// Smoke testing
// ─────────────────────────────────────────────

it('loads all diagnostic bacteriologique pages without JavaScript errors', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $analysis = diagnosticBacteriologiqueAnalysis($user, $breeder);

    $this->actingAs($user);

    visit([
        '/analyses/diagnostic-bacteriologique',
        '/analyses/diagnostic-bacteriologique/create',
        '/analyses/'.$analysis->id,
        '/analyses/'.$analysis->id.'/edit',
    ])->assertNoSmoke();
});

// ─────────────────────────────────────────────
// Index
// ─────────────────────────────────────────────

it('displays the diagnostic bacteriologique list with module title and create button', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    diagnosticBacteriologiqueAnalysis($user, $breeder, ['animal_nom' => 'Jasmine', 'intervenant' => 'Dr Martin']);

    $this->actingAs($user);

    visit('/analyses/diagnostic-bacteriologique')
        ->assertSee('Diagnostic bacteriologique')
        ->assertSee('Nouvelle analyse')
        ->assertSee('GAEC du Val')
        ->assertSee('Jasmine')
        ->assertSee('Dr Martin')
        ->assertNoJavaScriptErrors();
});

it('does not show diagnostic bacteriologique analyses from other users', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherBreeder = Breeder::factory()->create(['user_id' => $otherUser->id]);
    diagnosticBacteriologiqueAnalysis($otherUser, $otherBreeder, ['animal_nom' => 'Milka']);

    $this->actingAs($user);

    visit('/analyses/diagnostic-bacteriologique')
        ->assertSee('Diagnostic bacteriologique')
        ->assertDontSee('Milka')
        ->assertNoJavaScriptErrors();
});

// ─────────────────────────────────────────────
// Create
// ─────────────────────────────────────────────

it('creates a new diagnostic bacteriologique analysis through the browser form', function () {
    $user = User::factory()->create();
    Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);

    $this->actingAs($user);

    visit('/analyses/diagnostic-bacteriologique/create')
        ->assertSee('Nouvelle analyse')
        ->assertSee('Diagnostic bacteriologique')
        ->fill('breeder_id', 'GAEC')
        ->click('GAEC du Val')
        ->fill('animal_nom', 'Marguerite')
        ->fill('analyzed_at', '2026-05-18')
        ->fill('intervenant', 'Dr Coutrot')
        ->fill('bact_identification', 'Quartier AD')
        ->fill('bact_commemoratives', 'Mammite chronique')
        ->click("Enregistrer l'analyse")
        ->assertSee('Marguerite')
        ->assertSee('Dr Coutrot')
        ->assertNoJavaScriptErrors();
});

it('shows a validation error when no breeder is selected on diagnostic bacteriologique form', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    visit('/analyses/diagnostic-bacteriologique/create')
        ->fill('animal_nom', 'Marguerite')
        ->click("Enregistrer l'analyse")
        ->assertSee('Eleveur')
        ->assertNoJavaScriptErrors();
});

// ─────────────────────────────────────────────
// Show
// ─────────────────────────────────────────────

it('displays a diagnostic bacteriologique analysis with breeder, animal and edit button', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    $analysis = diagnosticBacteriologiqueAnalysis($user, $breeder, [
        'animal_nom' => 'Jasmine',
        'intervenant' => 'Dr Martin',
    ]);

    $this->actingAs($user);

    visit('/analyses/'.$analysis->id)
        ->assertSee('Diagnostic bacteriologique')
        ->assertSee('GAEC du Val')
        ->assertSee('Jasmine')
        ->assertSee('Dr Martin')
        ->assertSee('Modifier')
        ->assertNoJavaScriptErrors();
});

it('returns 404 when viewing a diagnostic bacteriologique analysis owned by another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherBreeder = Breeder::factory()->create(['user_id' => $otherUser->id]);
    $analysis = diagnosticBacteriologiqueAnalysis($otherUser, $otherBreeder);

    $this->actingAs($user);

    $this->get('/analyses/'.$analysis->id)->assertNotFound();
});

// ─────────────────────────────────────────────
// Edit
// ─────────────────────────────────────────────

it('edits a diagnostic bacteriologique analysis and persists the updated fields', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    $analysis = diagnosticBacteriologiqueAnalysis($user, $breeder, [
        'animal_nom' => 'Jasmine',
        'intervenant' => 'Dr Martin',
    ]);

    $this->actingAs($user);

    visit('/analyses/'.$analysis->id.'/edit')
        ->assertSee('Modifier analyse')
        ->fill('animal_nom', 'Marguerite')
        ->fill('intervenant', 'Dr Coutrot')
        ->fill('bact_commemoratives', 'Mammite aigue')
        ->click("Enregistrer l'analyse")
        ->assertSee('Marguerite')
        ->assertSee('Dr Coutrot')
        ->assertNoJavaScriptErrors();
});

it('navigates back to the show page when cancelling diagnostic bacteriologique edit', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $analysis = diagnosticBacteriologiqueAnalysis($user, $breeder);

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

it('deletes a diagnostic bacteriologique analysis after confirming the dialog', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    diagnosticBacteriologiqueAnalysis($user, $breeder, ['animal_nom' => 'Jasmine']);

    $this->actingAs($user);

    $page = visit('/analyses/diagnostic-bacteriologique');
    $page->assertSee('Jasmine');

    $page->script('() => { window.confirm = () => true; return true; }');
    $page->script('() => { document.querySelector("[title=\'Supprimer\']").click(); return true; }');
    $page->waitForEvent('networkidle');

    $page->assertDontSee('Jasmine')
        ->assertSee('Diagnostic bacteriologique')
        ->assertNoJavaScriptErrors();
});

it('keeps the analysis when the user cancels the diagnostic bacteriologique deletion dialog', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    diagnosticBacteriologiqueAnalysis($user, $breeder, ['animal_nom' => 'Jasmine']);

    $this->actingAs($user);

    $page = visit('/analyses/diagnostic-bacteriologique');
    $page->assertSee('Jasmine');

    $page->script('() => { window.confirm = () => false; return true; }');
    $page->script('() => { document.querySelector("[title=\'Supprimer\']").click(); return true; }');
    $page->waitForEvent('networkidle');

    $page->assertSee('Jasmine')
        ->assertNoJavaScriptErrors();
});
