<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;

dataset('petit ruminant parameters', [
    'brebis Lacaune en traite' => ['brebis_laitiere', ['race' => 'lacaune', 'poids_vif' => 70, 'parite' => 2, 'jours_lactation' => 60, 'lait_objectif' => 2.5], 'UFL', 'UEM'],
    'brebis Manech en traite' => ['brebis_laitiere', ['race' => 'manech', 'poids_vif' => 60, 'parite' => 2, 'jours_lactation' => 60, 'lait_objectif' => 2.0], 'UFL', 'UEM'],
    'brebis allaitante' => ['brebis_allaitante', ['race' => 'autre', 'poids_vif' => 65, 'parite' => 2, 'jours_lactation' => 14, 'nombre_jeunes' => 2, 'gmq_portee' => 350], 'UFL', 'UEM'],
    'brebis laitière tarie' => ['brebis_laitiere', ['race' => 'lacaune', 'poids_vif' => 70, 'parite' => 2, 'jours_lactation' => 0, 'lait_objectif' => 2.5], 'UFL', 'UEM'],
    'brebis allaitante tarie' => ['brebis_allaitante', ['race' => 'autre', 'poids_vif' => 65, 'parite' => 2, 'jours_lactation' => 0], 'UFL', 'UEM'],
    'brebis laitière gestante' => ['brebis_laitiere', ['race' => 'lacaune', 'poids_vif' => 70, 'parite' => 2, 'jours_lactation' => 0, 'jours_gestation' => 126, 'nombre_jeunes' => 2, 'poids_portee' => 8], 'UFL', 'UEM'],
    'brebis allaitante gestante' => ['brebis_allaitante', ['race' => 'autre', 'poids_vif' => 65, 'parite' => 2, 'jours_lactation' => 0, 'jours_gestation' => 126, 'nombre_jeunes' => 2, 'poids_portee' => 8], 'UFL', 'UEM'],
    'agneau' => ['agneau_croissance', ['race' => 'autre', 'poids_vif' => 30, 'gmq' => 250, 'age_jours' => 100, 'sexe' => 'male', 'poids_adulte' => 70], 'UFV', 'kg MS'],
    'agnelle engraissement' => ['agneau_croissance', ['race' => 'autre', 'poids_vif' => 25, 'gmq' => 200, 'age_jours' => 100, 'sexe' => 'femelle', 'poids_adulte' => 65], 'UFV', 'kg MS'],
    'chèvre Alpine' => ['chevre_laitiere', ['race' => 'alpine', 'poids_vif' => 60, 'parite' => 2, 'jours_lactation' => 60, 'lait_objectif' => 3.5], 'UFL', 'UEL'],
    'chèvre Saanen' => ['chevre_laitiere', ['race' => 'saanen', 'poids_vif' => 70, 'parite' => 2, 'jours_lactation' => 35, 'lait_objectif' => 4], 'UFL', 'UEL'],
    'chèvre tarie' => ['chevre_laitiere', ['race' => 'autre', 'poids_vif' => 60, 'parite' => 2, 'jours_lactation' => 0, 'lait_objectif' => 3.5, 'lait_potentiel' => 4], 'UFL', 'UEL'],
    'chèvre gestante' => ['chevre_laitiere', ['race' => 'alpine', 'poids_vif' => 60, 'parite' => 2, 'jours_lactation' => 0, 'jours_gestation' => 140, 'nombre_jeunes' => 2, 'poids_portee' => 8.3], 'UFL', 'UEL'],
    'chevrette Alpine' => ['chevrette_croissance', ['race' => 'alpine', 'poids_vif' => 27.7, 'gmq' => 127, 'age_jours' => 152, 'sexe' => 'femelle', 'poids_adulte' => 70], 'UFL', 'UEL'],
    'chevrette Saanen' => ['chevrette_croissance', ['race' => 'saanen', 'poids_vif' => 28.6, 'gmq' => 141, 'age_jours' => 152, 'sexe' => 'femelle', 'poids_adulte' => 80], 'UFL', 'UEL'],
    'chevrette autre' => ['chevrette_croissance', ['race' => 'autre', 'poids_vif' => 27.7, 'gmq' => 100, 'age_jours' => 152, 'sexe' => 'femelle', 'poids_adulte' => 70], 'UFL', 'UEL'],
]);

test('small ruminants complete creation, feeding, results, mixtures, ad libitum and duplication', function (string $category, array $parameters, string $energyUnit, string $intakeUnit) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    Pdf::fake();
    $this->actingAs($user)->post(route('plans.rations.store', $plan), ['nom' => 'Lot test', 'categorie_animal' => $category])->assertSessionHasNoErrors();
    $ration = $plan->rations()->sole();
    $description = route('plans.rations.description', [$plan, $ration]);
    $composition = route('plans.rations.composition', [$plan, $ration]);
    $this->get($description)->assertOk();
    $this->put(route('plans.rations.description.update', [$plan, $ration]), [
        'nom' => $ration->nom, 'categorie_animal' => $category, 'jours_gestation' => 0,
        'mois_lactation' => 3, 'mois_gestation' => 0, ...$parameters,
    ])->assertSessionHasNoErrors()->assertRedirect($composition);
    $ration->refresh();
    expect($ration->mois_lactation)->toBeNull();
    $this->get($composition)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('resultats.besoins.ci', fn ($value) => is_finite($value) && $value > 0));
    if (isset($parameters['jours_lactation']) && $parameters['jours_lactation'] === 0) {
        expect($ration->lait_objectif)->toBeNull();
        expect($ration->lait_potentiel)->toBeNull();
    }
    $forage = Aliment::factory()->systemique()->create([
        'usage_aliment' => 1, 'type' => 'fourrage', 'ms' => 85, 'ufl' => 0.8, 'ufv' => 0.9, 'pdi' => 100,
        'uel' => 1.1, 'uem' => 1.2, 'eb' => null, 'mo' => 900, 'mat' => 150, 'd_mo' => 65,
    ]);
    $this->from($composition)->post(route('plans.rations.aliments.add', [$plan, $ration]), [
        'aliment_id' => $forage->id, 'quantite' => 1.5, 'is_mb' => false, 'is_volonte' => false,
    ])->assertSessionHasNoErrors()->assertRedirect($composition);
    $this->get(route('plans.rations.resultats', [$plan, $ration]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('resultats.meta.unite_fourragere', $energyUnit)->where('resultats.meta.unite_encombrement', $intakeUnit)
        ->where('resultats.apports.uf', $energyUnit === 'UFV' ? 1.35 : 1.2)
        ->where('resultats.besoins.uf_total', fn ($value) => is_finite($value) && $value > 0)
        ->where('resultats.besoins.pdi_total', fn ($value) => is_finite($value) && $value > 0));
    $line = $ration->rationAliments()->sole();
    $this->put(route('plans.rations.aliments.update', [$plan, $ration, $line]), ['quantite' => 2, 'is_mb' => true, 'is_volonte' => false])->assertSessionHasNoErrors();
    $this->get($composition)->assertOk()->assertInertia(fn (Assert $page) => $page->where('resultats.apports.ms', 1.7));

    $grain = Aliment::factory()->systemique()->create(['usage_aliment' => 1, 'type' => 'concentre', 'ms' => 88, 'ufl' => 1.1, 'ufv' => 1.0, 'pdi' => 110, 'eb' => null, 'mo' => 960, 'mat' => 160, 'd_mo' => 80]);
    $this->from($composition)->post(route('plans.rations.melanges.store', [$plan, $ration]), ['nom' => 'Mélange du lot'])->assertSessionHasNoErrors()->assertRedirect($composition);
    $mix = $ration->melanges()->sole();
    $this->post(route('plans.rations.melanges.aliments.add', [$plan, $ration, $mix]), ['aliment_id' => $grain->id, 'quantite' => 1, 'is_mb' => false])->assertSessionHasNoErrors();
    $ingredient = $mix->melangeAliments()->sole();
    $this->put(route('plans.rations.melanges.aliments.update', [$plan, $ration, $mix, $ingredient]), ['quantite' => 2, 'is_mb' => true])->assertSessionHasNoErrors();
    $this->put(route('plans.rations.melanges.update', [$plan, $ration, $mix]), ['quantite' => 0.4, 'is_mb' => false, 'is_volonte' => false])->assertSessionHasNoErrors();
    $this->put(route('plans.rations.aliments.update', [$plan, $ration, $line]), ['is_mb' => false, 'is_volonte' => true])->assertSessionHasNoErrors();
    $this->get($composition)->assertOk()->assertInertia(function (Assert $page) {
        $page->where('iterations_volonte', fn ($iterations) => $iterations > 0 && $iterations < 80)
            ->where('resultats.bilans.ue', fn ($value) => abs($value) <= 0.01)
            ->where('resultats', function ($results) {
                array_walk_recursive($results, function ($value) {
                    if (is_numeric($value)) {
                        expect(is_finite((float) $value))->toBeTrue();
                    }
                });

                return true;
            });
    });
    $this->get(route('plans.rations.pdf', [$plan, $ration]))->assertOk();
    Pdf::assertRespondedWithPdf(function ($document) use ($energyUnit) {
        expect($document->viewData['resultats']['meta']['unite_fourragere'])->toBe($energyUnit);
        expect(view($document->viewName, $document->viewData)->render())->toContain($energyUnit);

        return true;
    });
    $this->post(route('plans.rations.duplicate', [$plan, $ration]))->assertRedirect();
    $copy = $plan->rations()->latest('id')->first();
    $this->get(route('plans.rations.composition', [$plan, $copy]))->assertOk();
    $this->delete(route('plans.rations.melanges.aliments.remove', [$plan, $ration, $mix, $ingredient]))->assertRedirect();
    $this->delete(route('plans.rations.melanges.destroy', [$plan, $ration, $mix]))->assertRedirect();
    $this->delete(route('plans.rations.aliments.remove', [$plan, $ration, $line]))->assertRedirect();
    $this->get($composition)->assertOk();
})->with('petit ruminant parameters');

test('incomplete small ruminant rations return to their parameter form', function (string $category) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'categorie_animal' => $category, 'race' => null, 'poids_vif' => null, 'age_jours' => null, 'sexe' => null, 'poids_adulte' => null, 'gmq' => null, 'parite' => null, 'jours_lactation' => null]);
    $this->actingAs($user);
    foreach (['composition', 'resultats', 'pdf'] as $page) {
        $this->get(route("plans.rations.{$page}", [$plan, $ration]))->assertRedirect(route('plans.rations.description', [$plan, $ration]))->assertSessionHasErrors(['race', 'poids_vif']);
    }
})->with(['brebis_laitiere', 'brebis_allaitante', 'agneau_croissance', 'chevre_laitiere', 'chevrette_croissance']);

test('small ruminant parameter errors stay on the form', function (string $category, array $parameters, string $field) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    $this->actingAs($user)->put(route('plans.rations.description.update', [$plan, $ration]), [
        'nom' => $ration->nom, 'categorie_animal' => $category, 'race' => 'autre', 'poids_vif' => 30,
        'age_jours' => 152, 'sexe' => 'femelle', 'poids_adulte' => 70, 'gmq' => 127,
        'parite' => 2, 'jours_lactation' => 0, ...$parameters,
    ])->assertSessionHasErrors($field);
})->with([
    ['chevrette_croissance', ['sexe' => 'male'], 'sexe'],
    ['chevrette_croissance', ['race' => 'lacaune'], 'race'],
    ['chevrette_croissance', ['age_jours' => 731], 'age_jours'],
    ['agneau_croissance', ['poids_adulte' => 20], 'poids_adulte'],
    ['brebis_laitiere', ['jours_lactation' => 141, 'lait_objectif' => 2], 'jours_lactation'],
    ['brebis_allaitante', ['jours_lactation' => 21], 'gmq_portee'],
    ['brebis_allaitante', ['jours_gestation' => 148, 'nombre_jeunes' => 2, 'poids_portee' => 8], 'jours_gestation'],
    ['chevre_laitiere', ['jours_gestation' => 151, 'nombre_jeunes' => 2, 'poids_portee' => 8], 'jours_gestation'],
]);
