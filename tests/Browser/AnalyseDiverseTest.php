<?php

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\User;
use App\Support\VeterinaryModules;

function analyseDiverseAnalysis(User $user, Breeder $breeder, array $attributes = []): Analysis
{
    $settings = VeterinaryModules::defaultSettings('analyse-diverse');

    return Analysis::factory()->create(array_merge([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'analyse-diverse',
        'animal_nom' => 'Jasmine',
        'intervenant' => 'Dr Martin',
        'payload' => VeterinaryModules::payloadTemplate('analyse-diverse', $settings),
        'settings_snapshot' => $settings,
    ], $attributes));
}

// ─────────────────────────────────────────────
// Smoke testing
// ─────────────────────────────────────────────

it('loads all analyses diverses pages without JavaScript errors', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $analysis = analyseDiverseAnalysis($user, $breeder);

    $this->actingAs($user);

    visit([
        '/analyses/analyse-diverse',
        '/analyses/analyse-diverse/create',
        '/analyses/'.$analysis->id,
        '/analyses/'.$analysis->id.'/edit',
    ])->assertNoSmoke();
});

// ─────────────────────────────────────────────
// Index
// ─────────────────────────────────────────────

it('displays the analyses diverses list with module title and create button', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    analyseDiverseAnalysis($user, $breeder, ['animal_nom' => 'Jasmine', 'intervenant' => 'Dr Martin']);

    $this->actingAs($user);

    visit('/analyses/analyse-diverse')
        ->assertSee('Analyses diverses')
        ->assertSee('Nouvelle analyse')
        ->assertSee('GAEC du Val')
        ->assertSee('Jasmine')
        ->assertSee('Dr Martin')
        ->assertNoJavaScriptErrors();
});

it('does not show analyses diverses from other users', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherBreeder = Breeder::factory()->create(['user_id' => $otherUser->id]);
    analyseDiverseAnalysis($otherUser, $otherBreeder, ['animal_nom' => 'Milka']);

    $this->actingAs($user);

    visit('/analyses/analyse-diverse')
        ->assertSee('Analyses diverses')
        ->assertDontSee('Milka')
        ->assertNoJavaScriptErrors();
});

// ─────────────────────────────────────────────
// Create
// ─────────────────────────────────────────────

it('creates a new analyse diverse through the browser form', function () {
    $user = User::factory()->create();
    Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);

    $this->actingAs($user);

    visit('/analyses/analyse-diverse/create')
        ->assertSee('Nouvelle analyse')
        ->assertSee('Analyses diverses')
        ->fill('breeder_id', 'GAEC')
        ->click('GAEC du Val')
        ->fill('animal_nom', 'Marguerite')
        ->fill('analyzed_at', '2026-05-18')
        ->fill('intervenant', 'Dr Coutrot')
        ->fill('commemoratifs', 'Diarrhée aigue depuis 3 jours')
        ->fill('commentaires', 'A surveiller - recontroler dans 15 jours')
        ->click("Enregistrer l'analyse")
        ->assertSee('Marguerite')
        ->assertSee('Dr Coutrot')
        ->assertNoJavaScriptErrors();
});

it('shows a validation error when no breeder is selected on analyse diverse form', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    visit('/analyses/analyse-diverse/create')
        ->fill('animal_nom', 'Marguerite')
        ->click("Enregistrer l'analyse")
        ->assertSee('Eleveur')
        ->assertNoJavaScriptErrors();
});

// ─────────────────────────────────────────────
// Show
// ─────────────────────────────────────────────

it('displays an analyse diverse with breeder, animal and edit button', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    $analysis = analyseDiverseAnalysis($user, $breeder, [
        'animal_nom' => 'Jasmine',
        'intervenant' => 'Dr Martin',
    ]);

    $this->actingAs($user);

    visit('/analyses/'.$analysis->id)
        ->assertSee('Analyses diverses')
        ->assertSee('GAEC du Val')
        ->assertSee('Jasmine')
        ->assertSee('Dr Martin')
        ->assertSee('Modifier')
        ->assertNoJavaScriptErrors();
});

it('returns 404 when viewing an analyse diverse owned by another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherBreeder = Breeder::factory()->create(['user_id' => $otherUser->id]);
    $analysis = analyseDiverseAnalysis($otherUser, $otherBreeder);

    $this->actingAs($user);

    $this->get('/analyses/'.$analysis->id)->assertNotFound();
});

// ─────────────────────────────────────────────
// Edit
// ─────────────────────────────────────────────

it('edits an analyse diverse and persists the updated fields', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    $analysis = analyseDiverseAnalysis($user, $breeder, [
        'animal_nom' => 'Jasmine',
        'intervenant' => 'Dr Martin',
    ]);

    $this->actingAs($user);

    visit('/analyses/'.$analysis->id.'/edit')
        ->assertSee('Modifier analyse')
        ->fill('animal_nom', 'Marguerite')
        ->fill('intervenant', 'Dr Coutrot')
        ->fill('commentaires', 'Commentaires mis a jour')
        ->click("Enregistrer l'analyse")
        ->assertSee('Marguerite')
        ->assertSee('Dr Coutrot')
        ->assertNoJavaScriptErrors();
});

it('navigates back to the show page when cancelling analyse diverse edit', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $analysis = analyseDiverseAnalysis($user, $breeder);

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

it('deletes an analyse diverse after confirming the dialog', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    analyseDiverseAnalysis($user, $breeder, ['animal_nom' => 'Jasmine']);

    $this->actingAs($user);

    $page = visit('/analyses/analyse-diverse');
    $page->assertSee('Jasmine');

    $page->script('() => { window.confirm = () => true; return true; }');
    $page->script('() => { document.querySelector("[title=\'Supprimer\']").click(); return true; }');
    $page->waitForEvent('networkidle');

    $page->assertDontSee('Jasmine')
        ->assertSee('Analyses diverses')
        ->assertNoJavaScriptErrors();
});

it('keeps the analysis when the user cancels the analyse diverse deletion dialog', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    analyseDiverseAnalysis($user, $breeder, ['animal_nom' => 'Jasmine']);

    $this->actingAs($user);

    $page = visit('/analyses/analyse-diverse');
    $page->assertSee('Jasmine');

    $page->script('() => { window.confirm = () => false; return true; }');
    $page->script('() => { document.querySelector("[title=\'Supprimer\']").click(); return true; }');
    $page->waitForEvent('networkidle');

    $page->assertSee('Jasmine')
        ->assertNoJavaScriptErrors();
});
