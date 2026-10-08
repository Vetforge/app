<?php

use App\Models\Aliment;
use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\Melange;
use App\Models\MelangeAliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Models\User;
use App\Support\VeterinaryModules;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

it('lists only breeders owned by the authenticated user', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    Breeder::factory()->create(['name' => 'EARL Externe']);

    $this->actingAs($user)
        ->get(route('breeders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('breeders/Index')
            ->has('breeders.data', 1)
            ->where('breeders.data.0.id', $breeder->id)
            ->where('breeders.data.0.name', 'GAEC du Val')
        );
});

it('shows a breeder with all of its analyses', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'coproscopie-parasitaire',
        'animal_nom' => 'Vache 123',
        'analyzed_at' => '2026-07-10',
    ]);
    Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'hemogramme',
        'animal_nom' => 'Titine',
        'analyzed_at' => '2026-07-12',
    ]);
    Analysis::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('breeders.show', $breeder))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('breeders/Show')
            ->where('breeder.id', $breeder->id)
            ->where('breeder.analyses_count', 2)
            ->has('analyses.data', 2)
            ->where('analyses.data.0.label', 'Hemogramme — Titine')
            ->where('analyses.data.1.label', 'Coproscopie — Vache 123')
        );
});

it('does not show another users breeder analyses', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create();

    $this->actingAs($user)
        ->get(route('breeders.show', $breeder))
        ->assertNotFound();
});

it('includes every analysis and report module in the breeder dossier', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $modules = array_keys(VeterinaryModules::all());

    foreach ($modules as $module) {
        Analysis::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'module' => $module]);
    }

    $this->actingAs($user)->get(route('breeders.show', $breeder))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('breeder.analyses_count', count($modules))
            ->has('analyses.data', count($modules))
            ->where('analyses.data', fn ($rows) => collect($rows)->pluck('module')->sort()->values()->all() === collect($modules)->sort()->values()->all())
        );
});

it('shows only the breeders owned analyses reports rations and aliments', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $otherBreeder = Breeder::factory()->create(['user_id' => $user->id]);
    $otherUser = User::factory()->create();
    $report = Analysis::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'module' => 'compte-rendu']);
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    $aliment = Aliment::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'usage_aliment' => 2]);

    foreach ([['user_id' => $user->id, 'breeder_id' => $otherBreeder->id], ['user_id' => $otherUser->id, 'breeder_id' => $breeder->id], ['user_id' => $user->id, 'breeder_id' => null]] as $attributes) {
        if ($attributes['breeder_id'] !== null) {
            Analysis::factory()->create($attributes);
        }
        Aliment::factory()->create($attributes);
        $excludedPlan = PlanRationnement::factory()->create($attributes);
        Ration::factory()->create(['plan_rationnement_id' => $excludedPlan->id]);
    }

    $this->actingAs($user)->get(route('breeders.show', $breeder))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('breeder.analyses_count', 1)
            ->where('breeder.rations_count', 1)
            ->where('breeder.aliments_count', 1)
            ->has('analyses.data', 1)
            ->where('analyses.data.0.id', $report->id)
            ->where('analyses.data.0.module_type', 'rapport')
            ->where('analyses.data.0.url', route('analyses.show', $report, false))
            ->has('rations.data', 1)
            ->where('rations.data.0.id', $ration->id)
            ->where('rations.data.0.plan_rationnement.id', $plan->id)
            ->has('aliments.data', 1)
            ->where('aliments.data.0.id', $aliment->id)
        );
});

it('paginates rations and aliments independently from analyses', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id]);
    Analysis::factory()->count(30)->create(['user_id' => $user->id, 'breeder_id' => $breeder->id]);
    Ration::factory()->count(30)->create(['plan_rationnement_id' => $plan->id]);
    Aliment::factory()->count(30)->create(['user_id' => $user->id, 'breeder_id' => $breeder->id]);

    $this->actingAs($user)->get(route('breeders.show', $breeder))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('analyses.data', 25)->has('rations.data', 25)->has('aliments.data', 25)
            ->where('rations.total', 30)->where('aliments.total', 30)
            ->where('breeder.rations_count', 30)->where('breeder.aliments_count', 30)
        );

    $this->get(route('breeders.show', ['breeder' => $breeder, 'rations_page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rations.data', 5)->where('rations.current_page', 2)
            ->has('analyses.data', 25)->where('analyses.current_page', 1)
            ->has('aliments.data', 25)->where('aliments.current_page', 1)
        );

    $this->get(route('breeders.show', ['breeder' => $breeder, 'aliments_page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('aliments.data', 5)->where('aliments.current_page', 2)
            ->has('rations.data', 25)->where('rations.current_page', 1)
            ->has('analyses.data', 25)->where('analyses.current_page', 1)
        );
});

it('includes aliments used in rations and mixtures without direct breeder attribution only once', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'nom' => 'Plan génisses']);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'nom' => 'Ration génisses']);
    $melange = Melange::create(['ration_id' => $ration->id, 'nom' => 'Mélange ferme', 'ordre' => 1]);
    $direct = Aliment::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'libelle0' => 'Foin prairie']);
    $inRation = Aliment::factory()->create(['user_id' => $user->id, 'breeder_id' => null, 'usage_aliment' => 2, 'libelle0' => 'Orge ferme']);
    $inMixture = Aliment::factory()->create(['user_id' => $user->id, 'breeder_id' => null, 'usage_aliment' => 2, 'libelle0' => 'Maïs ensilage']);
    RationAliment::factory()->create(['ration_id' => $ration->id, 'aliment_id' => $direct->id]);
    RationAliment::factory()->create(['ration_id' => $ration->id, 'aliment_id' => $inRation->id]);
    MelangeAliment::factory()->create(['melange_id' => $melange->id, 'aliment_id' => $inMixture->id]);

    $otherBreeder = Breeder::factory()->create(['user_id' => $user->id]);
    $otherPlan = PlanRationnement::factory()->create(['user_id' => $user->id, 'breeder_id' => $otherBreeder->id]);
    $otherRation = Ration::factory()->create(['plan_rationnement_id' => $otherPlan->id]);
    $otherMixture = Melange::create(['ration_id' => $otherRation->id, 'nom' => 'Autre mélange', 'ordre' => 1]);
    $excluded = Aliment::factory()->create(['user_id' => $user->id, 'breeder_id' => null]);
    RationAliment::factory()->create(['ration_id' => $otherRation->id, 'aliment_id' => $excluded->id]);
    MelangeAliment::factory()->create(['melange_id' => $otherMixture->id, 'aliment_id' => $excluded->id]);

    $expected = collect([$direct->id, $inRation->id, $inMixture->id])->sort()->values()->all();
    $this->actingAs($user)->get(route('breeders.show', $breeder))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('breeder.aliments_count', 3)->has('aliments.data', 3)
            ->where('aliments.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all() === $expected)
        );

    $this->get(route('breeders.show', ['breeder' => $breeder, 'search' => 'ensilage']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rations.data', 0)->has('aliments.data', 1)
            ->where('aliments.data.0.id', $inMixture->id)
        );
});

it('searches the breeder reports rations plans and all aliment labels', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'nom' => 'Suivi été']);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'nom' => 'Lot laitier']);
    $otherPlan = PlanRationnement::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'nom' => 'Autre plan']);
    Ration::factory()->create(['plan_rationnement_id' => $otherPlan->id, 'nom' => 'Lot génisses']);
    $aliment = Aliment::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'libelle0' => 'Foin prairie', 'libelle4' => 'Récolte été']);
    Aliment::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'libelle0' => 'Orge', 'libelle1' => null]);
    $report = Analysis::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'module' => 'compte-rendu']);

    $this->actingAs($user)->get(route('breeders.show', ['breeder' => $breeder, 'search' => 'ÉTÉ']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rations.data', 1)->where('rations.data.0.id', $ration->id)
            ->has('aliments.data', 1)->where('aliments.data.0.id', $aliment->id)
            ->where('breeder.rations_count', 2)->where('breeder.aliments_count', 2)
        );

    $this->get(route('breeders.show', ['breeder' => $breeder, 'search' => 'laitier']))
        ->assertInertia(fn (Assert $page) => $page->has('rations.data', 1)->where('rations.data.0.id', $ration->id));
    $this->get(route('breeders.show', ['breeder' => $breeder, 'search' => 'compte-rendu']))
        ->assertInertia(fn (Assert $page) => $page->has('analyses.data', 1)->where('analyses.data.0.id', $report->id));
    $this->get(route('breeders.show', ['breeder' => $breeder, 'search' => '%']))
        ->assertInertia(fn (Assert $page) => $page->has('analyses.data', 0)->has('rations.data', 0)->has('aliments.data', 0));
});

it('searches breeder analyses by animal name and module label', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $copro = Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'coproscopie-parasitaire',
        'animal_nom' => 'Vache 123',
    ]);
    $hemogramme = Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'hemogramme',
        'animal_nom' => 'Titine',
    ]);

    $this->actingAs($user)
        ->get(route('breeders.show', ['breeder' => $breeder, 'search' => 'titine']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('analyses.data', 1)
            ->where('analyses.data.0.id', $hemogramme->id)
        );

    $this->actingAs($user)
        ->get(route('breeders.show', ['breeder' => $breeder, 'search' => 'copro']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('analyses.data', 1)
            ->where('analyses.data.0.id', $copro->id)
        );
});

it('paginates breeder analyses for infinite scroll', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    Analysis::factory()->count(30)->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
    ]);

    $this->actingAs($user)
        ->get(route('breeders.show', $breeder))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('analyses.data', 25)
            ->where('analyses.total', 30)
        );

    $this->actingAs($user)
        ->get(route('breeders.show', ['breeder' => $breeder, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('analyses.data', 5)
        );
});

it('creates breeders with names unique per user only', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Breeder::factory()->create(['user_id' => $otherUser->id, 'name' => 'GAEC du Val']);

    $this->actingAs($user)
        ->post(route('breeders.store'), [
            'name' => 'GAEC du Val',
            'city' => 'Aurillac',
            'email' => 'contact@example.test',
        ])
        ->assertRedirect();

    expect(Breeder::query()->where('user_id', $user->id)->where('name', 'GAEC du Val')->exists())->toBeTrue();

    $this->actingAs($user)
        ->post(route('breeders.store'), ['name' => 'GAEC du Val'])
        ->assertSessionHasErrors('name');
});

it('does not allow opening another users breeder', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create();

    $this->actingAs($user)
        ->get(route('breeders.edit', $breeder))
        ->assertNotFound();
});

it('imports csv breeders and reports invalid rows', function () {
    $user = User::factory()->create();
    Breeder::factory()->create([
        'user_id' => $user->id,
        'name' => 'GAEC Existant',
        'city' => 'Ancienne ville',
    ]);

    $csv = implode("\n", [
        'nom;adresse;code_postal;ville;telephone;email;numero_cheptel;notes',
        'GAEC Nouveau;1 rue des pres;15000;Aurillac;0102030405;nouveau@example.test;FR123;Note',
        'GAEC Existant;2 rue;12000;Rodez;;existant@example.test;FR456;',
        ';sans nom;12000;Ville;;;',
        'GAEC Email;3 rue;12000;Ville;;not-an-email;;',
    ]);

    $file = UploadedFile::fake()->createWithContent('eleveurs.csv', $csv);

    $this->actingAs($user)
        ->post(route('breeders.import'), ['file' => $file])
        ->assertRedirect(route('breeders.index'))
        ->assertSessionHas('import_errors');

    expect(Breeder::query()->where('user_id', $user->id)->where('name', 'GAEC Nouveau')->exists())->toBeTrue()
        ->and(Breeder::query()->where('user_id', $user->id)->where('name', 'GAEC Existant')->value('city'))->toBe('Rodez')
        ->and(Breeder::query()->where('user_id', $user->id)->where('name', 'GAEC Email')->exists())->toBeFalse();
});

it('creates a breeder from the quick creation endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('breeders.quick-store'), [
            'name' => 'GAEC Rapide',
            'city' => 'Aurillac',
            'herd_number' => 'FR123',
        ])
        ->assertCreated()
        ->assertJsonPath('breeder.name', 'GAEC Rapide')
        ->assertJsonPath('breeder.city', 'Aurillac')
        ->assertJsonPath('breeder.herd_number', 'FR123');

    expect(Breeder::query()->where('user_id', $user->id)->where('name', 'GAEC Rapide')->exists())->toBeTrue();
});

it('downloads the breeder import example csv', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('breeders.import-example'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())
        ->toContain('nom;adresse;code_postal;ville;telephone;email;numero_cheptel;notes')
        ->toContain('GAEC du Val');
});

it('deletes a breeder and its analyses for that user', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $analysis = Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
    ]);

    $this->actingAs($user)
        ->delete(route('breeders.destroy', $breeder))
        ->assertRedirect(route('breeders.index'));

    expect(Breeder::query()->find($breeder->id))->toBeNull()
        ->and(Analysis::query()->find($analysis->id))->toBeNull();
});
