<?php

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\User;
use App\Support\VeterinaryModules;

function compteRenduAnalysis(User $user, Breeder $breeder, array $attributes = []): Analysis
{
    $settings = VeterinaryModules::defaultSettings('compte-rendu');

    return Analysis::factory()->create(array_merge([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'compte-rendu',
        'animal_nom' => 'Jasmine',
        'intervenant' => 'Dr Martin',
        'payload' => VeterinaryModules::payloadTemplate('compte-rendu', $settings),
        'settings_snapshot' => $settings,
    ], $attributes));
}

// ─────────────────────────────────────────────
// Smoke testing
// ─────────────────────────────────────────────

it('loads all compte-rendu pages without JavaScript errors', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $analysis = compteRenduAnalysis($user, $breeder);

    $this->actingAs($user);

    visit([
        '/analyses/compte-rendu',
        '/analyses/compte-rendu/create',
        '/analyses/'.$analysis->id,
        '/analyses/'.$analysis->id.'/edit',
    ])->assertNoSmoke();
});

// ─────────────────────────────────────────────
// Index
// ─────────────────────────────────────────────

it('displays the compte-rendu list with module title and create button', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    compteRenduAnalysis($user, $breeder, ['animal_nom' => 'Jasmine', 'intervenant' => 'Dr Martin']);

    $this->actingAs($user);

    visit('/analyses/compte-rendu')
        ->assertSee('Compte-rendu')
        ->assertSee('Nouveau rapport')
        ->assertSee('GAEC du Val')
        ->assertSee('Jasmine')
        ->assertSee('Dr Martin')
        ->assertNoJavaScriptErrors();
});

it('does not show compte-rendu analyses from other users', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherBreeder = Breeder::factory()->create(['user_id' => $otherUser->id]);
    compteRenduAnalysis($otherUser, $otherBreeder, ['animal_nom' => 'Milka']);

    $this->actingAs($user);

    visit('/analyses/compte-rendu')
        ->assertSee('Compte-rendu')
        ->assertDontSee('Milka')
        ->assertNoJavaScriptErrors();
});

// ─────────────────────────────────────────────
// Create
// ─────────────────────────────────────────────

it('creates a new compte-rendu through the browser form', function () {
    $user = User::factory()->create();
    Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);

    $this->actingAs($user);

    visit('/analyses/compte-rendu/create')
        ->assertSee('Nouveau rapport')
        ->assertSee('Compte-rendu')
        ->fill('breeder_id', 'GAEC')
        ->click('GAEC du Val')
        ->fill('animal_nom', 'Marguerite')
        ->fill('analyzed_at', '2026-05-18')
        ->fill('intervenant', 'Dr Coutrot')
        ->click('Enregistrer le rapport')
        ->assertSee('Marguerite')
        ->assertSee('Dr Coutrot')
        ->assertNoJavaScriptErrors();
});

it('shows a validation error when no breeder is selected on compte-rendu form', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    visit('/analyses/compte-rendu/create')
        ->fill('animal_nom', 'Marguerite')
        ->click('Enregistrer le rapport')
        ->assertSee('Eleveur')
        ->assertNoJavaScriptErrors();
});

// ─────────────────────────────────────────────
// Show
// ─────────────────────────────────────────────

it('displays a compte-rendu with breeder, animal and edit button', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    $analysis = compteRenduAnalysis($user, $breeder, [
        'animal_nom' => 'Jasmine',
        'intervenant' => 'Dr Martin',
    ]);

    $this->actingAs($user);

    visit('/analyses/'.$analysis->id)
        ->assertSee('Compte-rendu')
        ->assertSee('GAEC du Val')
        ->assertSee('Jasmine')
        ->assertSee('Dr Martin')
        ->assertSee('Modifier')
        ->assertNoJavaScriptErrors();
});

it('returns 404 when viewing a compte-rendu owned by another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherBreeder = Breeder::factory()->create(['user_id' => $otherUser->id]);
    $analysis = compteRenduAnalysis($otherUser, $otherBreeder);

    $this->actingAs($user);

    $this->get('/analyses/'.$analysis->id)->assertNotFound();
});

// ─────────────────────────────────────────────
// Edit
// ─────────────────────────────────────────────

it('edits a compte-rendu and persists the updated fields', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    $analysis = compteRenduAnalysis($user, $breeder, [
        'animal_nom' => 'Jasmine',
        'intervenant' => 'Dr Martin',
    ]);

    $this->actingAs($user);

    visit('/analyses/'.$analysis->id.'/edit')
        ->assertSee('Modifier rapport')
        ->fill('animal_nom', 'Marguerite')
        ->fill('intervenant', 'Dr Coutrot')
        ->click('Enregistrer le rapport')
        ->assertSee('Marguerite')
        ->assertSee('Dr Coutrot')
        ->assertNoJavaScriptErrors();
});

it('navigates back to the show page when cancelling compte-rendu edit', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $analysis = compteRenduAnalysis($user, $breeder);

    $this->actingAs($user);

    visit('/analyses/'.$analysis->id.'/edit')
        ->assertSee('Modifier rapport')
        ->click('Annuler')
        ->assertSee('Compte-rendu #'.$analysis->id)
        ->assertNoJavaScriptErrors();
});

// ─────────────────────────────────────────────
// Delete
// ─────────────────────────────────────────────

it('deletes a compte-rendu after confirming the dialog', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    compteRenduAnalysis($user, $breeder, ['animal_nom' => 'Jasmine']);

    $this->actingAs($user);

    $page = visit('/analyses/compte-rendu');
    $page->assertSee('Jasmine');

    $page->script('() => { window.confirm = () => true; return true; }');
    $page->script('() => { document.querySelector("[title=\'Supprimer\']").click(); return true; }');
    $page->waitForEvent('networkidle');

    $page->assertDontSee('Jasmine')
        ->assertSee('Compte-rendu')
        ->assertNoJavaScriptErrors();
});

it('keeps the compte-rendu when the user cancels the deletion dialog', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    compteRenduAnalysis($user, $breeder, ['animal_nom' => 'Jasmine']);

    $this->actingAs($user);

    $page = visit('/analyses/compte-rendu');
    $page->assertSee('Jasmine');

    $page->script('() => { window.confirm = () => false; return true; }');
    $page->script('() => { document.querySelector("[title=\'Supprimer\']").click(); return true; }');
    $page->waitForEvent('networkidle');

    $page->assertSee('Jasmine')
        ->assertNoJavaScriptErrors();
});
