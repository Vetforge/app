<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;
use App\Services\RationCalculator;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;

test('every bovine reference can complete creation, description, composition and results', function (int $reference, int $weight) {
    $category = $reference <= 9 ? 'bovin_engraissement' : 'bovin_croissance';
    $unit = $reference <= 9 ? 'UFV' : 'UFL';
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $this->actingAs($user);

    $this->post(route('plans.rations.store', $plan), [
        'nom' => "Référence {$reference}", 'categorie_animal' => $category,
    ])->assertSessionHasNoErrors();
    $ration = $plan->rations()->sole();
    $this->get(route('plans.rations.description', [$plan, $ration]))->assertOk();

    $this->put(route('plans.rations.description.update', [$plan, $ration]), [
        'nom' => $ration->nom, 'categorie_animal' => $category, 'race' => 'autre',
        'poids_vif' => $weight, 'reference_bovine' => $reference,
        'gmq' => $reference <= 9 ? 1200 : 800,
        // Valeurs envoyées par le formulaire alors que ces champs sont masqués.
        'mois_lactation' => 3, 'mois_gestation' => 0,
    ])->assertSessionHasNoErrors()->assertRedirect(route('plans.rations.composition', [$plan, $ration]));

    expect($ration->fresh()->mois_lactation)->toBeNull();
    expect($ration->fresh()->mois_gestation)->toBeNull();
    $this->get(route('plans.rations.composition', [$plan, $ration]))->assertOk();

    $forage = Aliment::factory()->systemique()->create([
        'usage_aliment' => 1, 'type' => 'fourrage', 'libelle0' => 'Foin bovins',
        'ms' => 85, 'ufl' => 0.8, 'ufv' => 0.7, 'pdi' => 90, 'ueb' => 1.1,
        'eb' => null, 'mo' => 900, 'd_mo' => 65, 'mat' => 120,
    ]);
    $this->from(route('plans.rations.composition', [$plan, $ration]))
        ->post(route('plans.rations.aliments.add', [$plan, $ration]), [
            'aliment_id' => $forage->id, 'quantite' => 6, 'is_mb' => false, 'is_volonte' => false,
        ])->assertSessionHasNoErrors()->assertRedirect();

    $this->get(route('plans.rations.resultats', [$plan, $ration]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rations/Resultats')
            ->where('resultats.meta.unite_fourragere', $unit)
            ->where('resultats.meta.unite_encombrement', 'UEB')
            ->where('resultats.besoins.uf_total', fn ($value) => is_finite($value) && $value > 0)
            ->where('resultats.besoins.pdi_total', fn ($value) => is_finite($value) && $value > 0)
            ->where('resultats.apports.uf', $reference <= 9 ? 4.2 : 4.8)
            ->missing('resultats.impacts.production_lait_attendue')
        );

    $line = $ration->rationAliments()->sole();
    $this->put(route('plans.rations.aliments.update', [$plan, $ration, $line]), [
        'quantite' => 7, 'is_mb' => false, 'is_volonte' => false,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect((float) $line->fresh()->quantite)->toBe(7.0);
    $this->put(route('plans.rations.aliments.update', [$plan, $ration, $line]), [
        'quantite' => 10, 'is_mb' => true, 'is_volonte' => false,
    ])->assertSessionHasNoErrors()->assertRedirect();
    $this->get(route('plans.rations.resultats', [$plan, $ration]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('resultats.apports.ms', 8.5)
            ->where('resultats.apports.uf', $reference <= 9 ? 5.95 : 6.8));
    $this->post(route('plans.rations.duplicate', [$plan, $ration]))->assertRedirect();
    $copy = $plan->rations()->latest('id')->first();
    $this->get(route('plans.rations.composition', [$plan, $copy]))->assertOk();
})->with([
    'taurillon Charolais' => [1, 450], 'taurillon Limousin' => [2, 450],
    'taurillon Salers' => [3, 450], 'taurillon laitier' => [4, 350],
    'bœuf tardif' => [5, 600], 'bœuf précoce' => [6, 600],
    'génisse tardive finition' => [7, 550], 'bœuf croisé' => [8, 550],
    'génisse croisée finition' => [9, 500], 'mâle tardif croissance' => [10, 400],
    'mâle précoce croissance' => [11, 350], 'génisse tardive croissance' => [12, 400],
    'génisse laitière' => [13, 350], 'génisse croisée croissance' => [14, 250],
]);

test('incomplete or incompatible bovine parameters return to the animal form', function (string $category, ?int $reference) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id, 'categorie_animal' => $category,
        'race' => 'autre', 'reference_bovine' => $reference, 'gmq' => null,
    ]);
    $this->actingAs($user);
    foreach (['composition', 'resultats', 'pdf'] as $page) {
        $this->get(route("plans.rations.{$page}", [$plan, $ration]))
            ->assertRedirect(route('plans.rations.description', [$plan, $ration]))
            ->assertSessionHasErrors(['reference_bovine', 'gmq']);
    }
})->with([
    ['bovin_croissance', null], ['bovin_engraissement', null],
    ['bovin_croissance', 1], ['bovin_engraissement', 10],
]);

test('bovine mixtures and ad libitum forage can be saved and recalculated without route errors', function (string $category, int $reference, int $gmq) {
    Pdf::fake();
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create([
        'plan_rationnement_id' => $plan->id, 'categorie_animal' => $category,
        'race' => 'charolaise', 'reference_bovine' => $reference,
        'poids_vif' => 450, 'gmq' => $gmq,
    ]);
    $forage = Aliment::factory()->systemique()->create([
        'usage_aliment' => 1, 'type' => 'fourrage', 'ms' => 85,
        'ufl' => 0.8, 'ufv' => 0.7, 'pdi' => 90, 'ueb' => 1.1,
        'eb' => null, 'mo' => 900, 'd_mo' => 65, 'mat' => 120,
    ]);
    $grain = Aliment::factory()->systemique()->create([
        'usage_aliment' => 1, 'type' => 'concentre', 'ms' => 88,
        'ufl' => 1.1, 'ufv' => 1.0, 'pdi' => 100,
        'eb' => null, 'mo' => 960, 'd_mo' => 80, 'mat' => 150,
    ]);
    $composition = route('plans.rations.composition', [$plan, $ration]);
    $this->actingAs($user)->from($composition);

    $this->post(route('plans.rations.melanges.store', [$plan, $ration]), ['nom' => 'Concentré ferme'])
        ->assertSessionHasNoErrors()->assertRedirect($composition);
    $mix = $ration->melanges()->sole();
    $this->post(route('plans.rations.melanges.aliments.add', [$plan, $ration, $mix]), [
        'aliment_id' => $grain->id, 'quantite' => 1, 'is_mb' => false,
    ])->assertSessionHasNoErrors()->assertRedirect($composition);
    $ingredient = $mix->melangeAliments()->sole();
    $this->put(route('plans.rations.melanges.aliments.update', [$plan, $ration, $mix, $ingredient]), [
        'quantite' => 2, 'is_mb' => false,
    ])->assertSessionHasNoErrors()->assertRedirect($composition);
    $this->put(route('plans.rations.melanges.update', [$plan, $ration, $mix]), [
        'quantite' => 2, 'is_mb' => false, 'is_volonte' => false,
    ])->assertSessionHasNoErrors()->assertRedirect($composition);
    $this->post(route('plans.rations.aliments.add', [$plan, $ration]), [
        'aliment_id' => $forage->id, 'is_mb' => false, 'is_volonte' => true,
    ])->assertSessionHasNoErrors()->assertRedirect($composition);

    $this->get($composition)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('iterations_volonte', fn ($iterations) => $iterations > 0 && $iterations < 80)
        ->where('resultats.bilans.ue', fn ($balance) => abs($balance) <= 0.01)
    );
    $result = RationCalculator::calculer($ration->fresh()->load([
        'planRationnement', 'rationAliments.aliment', 'melanges.melangeAliments.aliment',
    ]));
    array_walk_recursive($result, function ($value): void {
        if (is_numeric($value)) {
            expect(is_finite((float) $value))->toBeTrue();
        }
    });
    expect($result['apports']['ms'])->toBeGreaterThan(2.0);
    $pdf = view('pdf.ration', [
        'plan' => $plan, 'ration' => $ration->fresh()->load(['rationAliments.aliment', 'melanges.melangeAliments.aliment']),
        'resultats' => $result, 'iterations_volonte' => 0,
    ])->render();
    expect($pdf)->toContain($reference <= 9 ? 'UFV' : 'UFL')
        ->not->toContain('Lait permis et objectif');
    $this->get(route('plans.rations.pdf', [$plan, $ration]))->assertOk();
    Pdf::assertRespondedWithPdf(function ($document) use ($reference) {
        expect($document->viewData['resultats']['meta']['unite_fourragere'])
            ->toBe($reference <= 9 ? 'UFV' : 'UFL');

        return true;
    });
    $this->delete(route('plans.rations.melanges.aliments.remove', [$plan, $ration, $mix, $ingredient]))
        ->assertRedirect($composition);
    $this->delete(route('plans.rations.melanges.destroy', [$plan, $ration, $mix]))->assertRedirect($composition);
    $line = $ration->rationAliments()->sole();
    $this->delete(route('plans.rations.aliments.remove', [$plan, $ration, $line]))->assertRedirect($composition);
    $this->get($composition)->assertOk();
})->with([
    ['bovin_croissance', 10, 800], ['bovin_engraissement', 1, 1400],
]);

test('bovine description rejects incompatible gains, references and weights with field errors', function (string $category, int $reference, int $weight, int $gmq, string $field) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    $this->actingAs($user)->put(route('plans.rations.description.update', [$plan, $ration]), [
        'nom' => $ration->nom, 'categorie_animal' => $category, 'race' => 'autre',
        'reference_bovine' => $reference, 'poids_vif' => $weight, 'gmq' => $gmq,
    ])->assertSessionHasErrors($field);
})->with([
    ['bovin_croissance', 10, 400, 1001, 'gmq'],
    ['bovin_engraissement', 1, 450, 1000, 'gmq'],
    ['bovin_croissance', 1, 400, 800, 'reference_bovine'],
    ['bovin_engraissement', 10, 450, 1400, 'reference_bovine'],
    ['bovin_croissance', 13, 1200, 800, 'poids_vif'],
]);
