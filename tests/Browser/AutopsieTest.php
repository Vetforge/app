<?php

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\User;
use App\Support\VeterinaryModules;

function autopsieAnalysis(User $user, Breeder $breeder, array $attributes = []): Analysis
{
    $settings = VeterinaryModules::defaultSettings('autopsie');

    return Analysis::factory()->create(array_merge([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'autopsie',
        'animal_nom' => 'Jasmine',
        'intervenant' => 'Dr Martin',
        'payload' => VeterinaryModules::payloadTemplate('autopsie', $settings),
        'settings_snapshot' => $settings,
    ], $attributes));
}

// ─────────────────────────────────────────────
// Smoke testing
// ─────────────────────────────────────────────

it('loads all autopsie pages without JavaScript errors', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $analysis = autopsieAnalysis($user, $breeder);

    $this->actingAs($user);

    visit([
        '/analyses/autopsie',
        '/analyses/autopsie/create',
        '/analyses/'.$analysis->id,
        '/analyses/'.$analysis->id.'/edit',
    ])->assertNoSmoke();
});

// ─────────────────────────────────────────────
// Index
// ─────────────────────────────────────────────

it('displays the autopsie list with module title and create button', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    autopsieAnalysis($user, $breeder, ['animal_nom' => 'Jasmine', 'intervenant' => 'Dr Martin']);

    $this->actingAs($user);

    visit('/analyses/autopsie')
        ->assertSee('Autopsie')
        ->assertSee('Nouveau rapport')
        ->assertSee('GAEC du Val')
        ->assertSee('Jasmine')
        ->assertSee('Dr Martin')
        ->assertNoJavaScriptErrors();
});

it('does not show autopsie analyses from other users', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherBreeder = Breeder::factory()->create(['user_id' => $otherUser->id]);
    autopsieAnalysis($otherUser, $otherBreeder, ['animal_nom' => 'Milka']);

    $this->actingAs($user);

    visit('/analyses/autopsie')
        ->assertSee('Autopsie')
        ->assertDontSee('Milka')
        ->assertNoJavaScriptErrors();
});

// ─────────────────────────────────────────────
// Create
// ─────────────────────────────────────────────

it('creates a new autopsie through the browser form', function () {
    $user = User::factory()->create();
    Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);

    $this->actingAs($user);

    visit('/analyses/autopsie/create')
        ->assertSee('Nouveau rapport')
        ->assertSee('Autopsie')
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

it('shows a validation error when no breeder is selected on autopsie form', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    visit('/analyses/autopsie/create')
        ->fill('animal_nom', 'Marguerite')
        ->click('Enregistrer le rapport')
        ->assertSee('Eleveur')
        ->assertNoJavaScriptErrors();
});

// ─────────────────────────────────────────────
// Show
// ─────────────────────────────────────────────

it('displays an autopsie with breeder, animal and edit button', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    $analysis = autopsieAnalysis($user, $breeder, [
        'animal_nom' => 'Jasmine',
        'intervenant' => 'Dr Martin',
    ]);

    $this->actingAs($user);

    visit('/analyses/'.$analysis->id)
        ->assertSee('Autopsie')
        ->assertSee('GAEC du Val')
        ->assertSee('Jasmine')
        ->assertSee('Dr Martin')
        ->assertSee('Modifier')
        ->assertNoJavaScriptErrors();
});

it('returns 404 when viewing an autopsie owned by another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherBreeder = Breeder::factory()->create(['user_id' => $otherUser->id]);
    $analysis = autopsieAnalysis($otherUser, $otherBreeder);

    $this->actingAs($user);

    $this->get('/analyses/'.$analysis->id)->assertNotFound();
});

// ─────────────────────────────────────────────
// Edit
// ─────────────────────────────────────────────

it('edits an autopsie and persists the updated fields', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    $analysis = autopsieAnalysis($user, $breeder, [
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

it('navigates back to the show page when cancelling autopsie edit', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $analysis = autopsieAnalysis($user, $breeder);

    $this->actingAs($user);

    visit('/analyses/'.$analysis->id.'/edit')
        ->assertSee('Modifier rapport')
        ->click('Annuler')
        ->assertSee('Autopsie #'.$analysis->id)
        ->assertNoJavaScriptErrors();
});

// ─────────────────────────────────────────────
// Delete
// ─────────────────────────────────────────────

it('deletes an autopsie after confirming the dialog', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    autopsieAnalysis($user, $breeder, ['animal_nom' => 'Jasmine']);

    $this->actingAs($user);

    $page = visit('/analyses/autopsie');
    $page->assertSee('Jasmine');

    $page->script('() => { window.confirm = () => true; return true; }');
    $page->script('() => { document.querySelector("[title=\'Supprimer\']").click(); return true; }');
    $page->waitForEvent('networkidle');

    $page->assertDontSee('Jasmine')
        ->assertSee('Autopsie')
        ->assertNoJavaScriptErrors();
});

it('keeps the autopsie when the user cancels the deletion dialog', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    autopsieAnalysis($user, $breeder, ['animal_nom' => 'Jasmine']);

    $this->actingAs($user);

    $page = visit('/analyses/autopsie');
    $page->assertSee('Jasmine');

    $page->script('() => { window.confirm = () => false; return true; }');
    $page->script('() => { document.querySelector("[title=\'Supprimer\']").click(); return true; }');
    $page->waitForEvent('networkidle');

    $page->assertSee('Jasmine')
        ->assertNoJavaScriptErrors();
});
