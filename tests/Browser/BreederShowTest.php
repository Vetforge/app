<?php

use App\Models\Aliment;
use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Models\User;

/**
 * Le nom d'un éleveur dans la liste doit ouvrir la page regroupant
 * toutes ses analyses, avec recherche et scroll infini.
 */
test('the breeder name links to the breeder analyses page', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'hemogramme',
        'animal_nom' => 'Titine',
    ]);

    $this->actingAs($user);

    visit(route('breeders.index', absolute: false))
        ->assertNoJavaScriptErrors()
        ->assertSee('GAEC du Val')
        ->click('GAEC du Val')
        ->assertPathIs(route('breeders.show', $breeder, absolute: false))
        ->assertNoJavaScriptErrors()
        ->assertSee('Hemogramme — Titine');
});

test('the breeder analyses page filters analyses with the search bar', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'hemogramme',
        'animal_nom' => 'Titine',
    ]);
    Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'coproscopie-parasitaire',
        'animal_nom' => 'Vache 123',
    ]);

    $this->actingAs($user);

    visit(route('breeders.show', $breeder, absolute: false))
        ->assertNoJavaScriptErrors()
        ->assertSee('Hemogramme — Titine')
        ->assertSee('Coproscopie — Vache 123')
        ->fill('search', 'titine')
        ->wait(1)
        ->assertSee('Hemogramme — Titine')
        ->assertDontSee('Coproscopie — Vache 123');
});

test('the breeder dossier shows reports rations and aliments with links to their pages', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    Analysis::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'module' => 'compte-rendu']);
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'nom' => 'Plan élevage', 'inra' => '2018']);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'nom' => 'Ration laitières']);
    $aliment = Aliment::factory()->create(['user_id' => $user->id, 'breeder_id' => null, 'code_inra' => null, 'usage_aliment' => 2, 'libelle0' => 'Foin élevage']);
    RationAliment::factory()->create(['ration_id' => $ration->id, 'aliment_id' => $aliment->id]);
    $this->actingAs($user);

    visit(route('breeders.show', $breeder, absolute: false))
        ->assertSee('Analyses et comptes rendus (1)')->assertSee('Compte-rendu')
        ->assertSee('Rations (1)')->assertSee('Aliments (1)')
        ->click('button[data-section="rations"]')->assertSee('Ration laitières')->assertSee('Plan élevage')
        ->assertNoJavaScriptErrors()
        ->click('Ration laitières')
        ->assertPathIs(route('plans.rations.composition', [$plan, $ration], absolute: false))
        ->assertNoJavaScriptErrors();

    visit(route('breeders.show', $breeder, absolute: false))
        ->click('button[data-section="aliments"]')->assertSee('Foin élevage')->assertSee('Aliment de ration')->assertNoJavaScriptErrors()
        ->click('Foin élevage')
        ->assertPathIs(route('aliments.edit', $aliment, absolute: false))
        ->assertNoJavaScriptErrors();
});

test('the breeder dossier searches all sections and clears old search results', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id]);
    Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'nom' => 'Ration prairie']);
    Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'nom' => 'Ration génisses']);
    Aliment::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'libelle0' => 'Foin prairie']);
    Aliment::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'libelle0' => 'Orge ferme']);
    $this->actingAs($user);

    visit(route('breeders.show', $breeder, absolute: false))
        ->click('button[data-section="aliments"]')->assertSee('Foin prairie')->assertSee('Orge ferme')
        ->fill('search', 'prairie')->wait(1)->assertSee('Foin prairie')->assertDontSee('Orge ferme')
        ->click('button[data-section="rations"]')->assertSee('Ration prairie')->assertDontSee('Ration génisses')
        ->fill('search', 'inexistant')->wait(1)->assertSee('Aucun élément ne correspond à cette recherche')->assertDontSee('Ration prairie')
        ->click('button[aria-label="Effacer la recherche"]')->wait(1)->assertSee('Ration prairie')->assertSee('Ration génisses')
        ->click('button[data-section="aliments"]')->assertSee('Foin prairie')->assertSee('Orge ferme')
        ->assertNoJavaScriptErrors();
});

test('the breeder dossier loads all pages when scrolling each section', function (string $section) {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $attributes = ['user_id' => $user->id, 'breeder_id' => $breeder->id];

    if ($section === 'analyses') {
        $items = Analysis::factory()->count(30)->create([...$attributes, 'module' => 'hemogramme', 'analyzed_at' => '2026-10-01']);
        $lastUrl = route('analyses.show', $items->first(), false);
    } elseif ($section === 'rations') {
        $plan = PlanRationnement::factory()->create($attributes);
        $items = Ration::factory()->count(30)->create(['plan_rationnement_id' => $plan->id]);
        $lastUrl = route('plans.rations.composition', [$plan, $items->first()], false);
    } else {
        $items = Aliment::factory()->count(30)->create($attributes);
        $lastUrl = route('aliments.edit', $items->first(), false);
    }

    $this->actingAs($user);
    $page = visit(route('breeders.show', $breeder, absolute: false))
        ->click('button[data-section="'.$section.'"]');

    $page->script('document.querySelectorAll("[data-testid=dossier-list] a").item(24)?.scrollIntoView()');
    $page->assertPresent('a[href="'.$lastUrl.'"]')->assertNoJavaScriptErrors();
})->with(['analyses', 'rations', 'aliments']);
