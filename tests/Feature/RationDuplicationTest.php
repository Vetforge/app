<?php

use App\Models\Aliment;
use App\Models\Breeder;
use App\Models\Melange;
use App\Models\MelangeAliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Models\User;
use App\Services\RationDuplicator;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $this->user->id]);
    $this->plan = PlanRationnement::factory()->create(['user_id' => $this->user->id, 'breeder_id' => $breeder->id]);
    $this->ration = Ration::factory()->create([
        'plan_rationnement_id' => $this->plan->id,
        'nom' => 'Ration été',
        'effectif' => 42,
        'lait_potentiel305j' => 9500,
        'poids_vif' => 650,
        'pourcentage_primipare' => 25,
        'nec' => 3.2,
        'tb_annuel' => 42.5,
        'tp_annuel' => 33.5,
        'activite' => 'plaine',
        'temperature_ambiante' => 18.5,
        'nec_velage' => 3.5,
        'ivv' => 390,
        'poids_veau_naissance' => 42,
        'age_velage' => 24,
        'age_jours' => 1200,
        'sexe' => 'femelle',
        'parite' => 2,
        'poids_adulte' => 700,
        'reference_bovine' => 1,
        'lait_potentiel' => 32.5,
        'lait_objectif305j' => 9000,
        'stade_moyen' => 120,
        'lait_objectif' => 30,
        'is_ration_semi_complete' => true,
        'ecart_variation_reserve' => -0.3,
        'strategie' => 'augmenter_lait',
        'lait_objectif_auge' => 25,
        'race' => 'montbeliarde',
        'mois_lactation' => 3.75,
        'mois_gestation' => 2.25,
        'categorie_animal' => 'vache_laitiere',
        'gmq' => 800,
        'stade_physiologique' => 'lactation',
        'jours_gestation' => 65,
        'jours_lactation' => 114,
        'nombre_jeunes' => 2,
        'poids_portee' => 8.5,
        'gmq_portee' => 350,
        'mfc' => 38.5,
        'mpc' => 32.4,
        'type_production_ovin' => 'lait',
    ]);

    foreach ([['quantite' => null, 'is_volonte' => true, 'is_mb' => true, 'ordre' => 7], ['quantite' => 0, 'is_volonte' => false, 'is_mb' => false, 'ordre' => 2]] as $attributes) {
        $aliment = Aliment::factory()->create([
            'user_id' => $this->user->id, 'breeder_id' => $breeder->id, 'usage_aliment' => 2,
            'type' => 'fourrage', 'prix' => 152.37, 'ms' => 32.4, 'ufl' => 0.91, 'ufl2007' => 0.89,
        ]);
        RationAliment::factory()->create(['ration_id' => $this->ration->id, 'aliment_id' => $aliment->id, ...$attributes]);
    }

    foreach ([['nom' => 'Mélange complet', 'quantite' => 12.3456, 'is_mb' => true, 'ordre' => 8], ['nom' => 'Correcteur', 'quantite' => 1.25, 'is_mb' => false, 'ordre' => 3]] as $attributes) {
        $melange = Melange::create(['ration_id' => $this->ration->id, 'is_volonte' => false, ...$attributes]);
        foreach ([['quantite' => 2.3456, 'is_mb' => true, 'ordre' => 4], ['quantite' => 0, 'is_mb' => false, 'ordre' => 1]] as $ingredient) {
            $aliment = Aliment::factory()->create(['user_id' => $this->user->id, 'usage_aliment' => 2, 'ufl' => 1.1234, 'pdi' => 125.5]);
            MelangeAliment::factory()->create(['melange_id' => $melange->id, 'aliment_id' => $aliment->id, ...$ingredient]);
        }
    }
    Melange::create(['ration_id' => $this->ration->id, 'nom' => 'Mélange vide', 'quantite' => null, 'ordre' => 9]);

    $this->attributes = fn (Model $model, array $except = []): array => collect($model->getRawOriginal())
        ->except(['id', 'created_at', 'updated_at', ...$except])->all();
});

it('duplicates every animal parameter, component and nutritional value', function (string $category, string $inra) {
    $this->ration->update(['categorie_animal' => $category]);
    $this->plan->update(['inra' => $inra]);
    $source = $this->ration->fresh(['rationAliments.aliment', 'melanges.melangeAliments.aliment']);

    $response = $this->actingAs($this->user)->post(route('plans.rations.duplicate', [$this->plan, $source]));

    $copy = $this->plan->rations()->whereKeyNot($source->id)->sole();
    $copy->load(['rationAliments.aliment', 'melanges.melangeAliments.aliment']);
    $response->assertRedirect(route('plans.rations.description', [$this->plan, $copy]))->assertSessionHas('success');

    expect(($this->attributes)($copy))->toBe(($this->attributes)($source))
        ->and($copy->rationAliments)->toHaveCount(2)
        ->and($copy->melanges)->toHaveCount(3);

    foreach ($source->rationAliments as $index => $component) {
        $copiedComponent = $copy->rationAliments[$index];
        expect($copiedComponent->id)->not->toBe($component->id)
            ->and($copiedComponent->aliment_id)->not->toBe($component->aliment_id)
            ->and(($this->attributes)($copiedComponent, ['ration_id', 'aliment_id']))->toBe(($this->attributes)($component, ['ration_id', 'aliment_id']))
            ->and(($this->attributes)($copiedComponent->aliment))->toBe(($this->attributes)($component->aliment));
    }
    foreach ($source->melanges as $index => $melange) {
        $copiedMelange = $copy->melanges[$index];
        expect($copiedMelange->id)->not->toBe($melange->id)
            ->and(($this->attributes)($copiedMelange, ['ration_id']))->toBe(($this->attributes)($melange, ['ration_id']))
            ->and($copiedMelange->melangeAliments)->toHaveCount($melange->melangeAliments->count());
        foreach ($melange->melangeAliments as $ingredientIndex => $ingredient) {
            $copiedIngredient = $copiedMelange->melangeAliments[$ingredientIndex];
            expect($copiedIngredient->id)->not->toBe($ingredient->id)
                ->and($copiedIngredient->aliment_id)->not->toBe($ingredient->aliment_id)
                ->and(($this->attributes)($copiedIngredient, ['melange_id', 'aliment_id']))->toBe(($this->attributes)($ingredient, ['melange_id', 'aliment_id']))
                ->and(($this->attributes)($copiedIngredient->aliment))->toBe(($this->attributes)($ingredient->aliment));
        }
    }
})->with([
    ['vache_laitiere', '2007'], ['vache_laitiere', '2018'], ['brebis_laitiere', '2018'], ['chevre_laitiere', '2018'], ['bovin_croissance', '2018'],
]);

it('keeps the source intact when editing or deleting copied components', function () {
    $source = $this->ration->fresh(['rationAliments.aliment', 'melanges.melangeAliments.aliment']);
    $copy = app(RationDuplicator::class)->duplicate($source);
    $copiedComponent = $copy->rationAliments()->first();
    $copiedIngredient = $copy->melanges()->first()->melangeAliments()->first();

    $copy->update(['nom' => 'Ration ajustée', 'poids_vif' => 700]);
    $copiedComponent->update(['quantite' => 25.5]);
    $copiedComponent->aliment->update(['ufl' => 0.5]);
    $copiedIngredient->update(['quantite' => 5.5]);
    $copiedIngredient->aliment->update(['pdi' => 200]);

    expect(($this->attributes)($source->fresh()))->toBe(($this->attributes)($source))
        ->and(($this->attributes)($source->rationAliments->first()->fresh()))->toBe(($this->attributes)($source->rationAliments->first()))
        ->and(($this->attributes)($source->rationAliments->first()->aliment->fresh()))->toBe(($this->attributes)($source->rationAliments->first()->aliment))
        ->and(($this->attributes)($source->melanges->first()->melangeAliments->first()->fresh()))->toBe(($this->attributes)($source->melanges->first()->melangeAliments->first()))
        ->and(($this->attributes)($source->melanges->first()->melangeAliments->first()->aliment->fresh()))->toBe(($this->attributes)($source->melanges->first()->melangeAliments->first()->aliment));

    $copiedComponent->delete();
    $copy->melanges->each->delete();
    foreach ($source->rationAliments as $component) {
        $this->assertModelExists($component);
        $this->assertModelExists($component->aliment);
    }
    foreach ($source->melanges as $melange) {
        $this->assertModelExists($melange);
        foreach ($melange->melangeAliments as $ingredient) {
            $this->assertModelExists($ingredient);
            $this->assertModelExists($ingredient->aliment);
        }
    }
});

it('duplicates an empty ration and preserves null parameters', function () {
    $empty = Ration::factory()->create(['plan_rationnement_id' => $this->plan->id, 'nom' => 'Ration vide', 'poids_vif' => null]);
    $this->actingAs($this->user)->post(route('plans.rations.duplicate', [$this->plan, $empty]))->assertRedirect();
    $copy = $this->plan->rations()->whereNotIn('id', [$this->ration->id, $empty->id])->sole();
    expect(($this->attributes)($copy))->toBe(($this->attributes)($empty->fresh()))
        ->and($copy->rationAliments)->toBeEmpty()->and($copy->melanges)->toBeEmpty();
});

it('preserves a mixture distributed at will', function () {
    $this->ration->rationAliments()->update(['is_volonte' => false]);
    $melange = $this->ration->melanges()->first();
    $melange->update(['is_volonte' => true, 'quantite' => null]);
    $copy = app(RationDuplicator::class)->duplicate($this->ration);
    expect(($this->attributes)($copy->melanges()->first(), ['ration_id']))->toBe(($this->attributes)($melange->fresh(), ['ration_id']));
});

it('forbids duplicating another veterinarians ration', function () {
    $this->actingAs(User::factory()->create())->post(route('plans.rations.duplicate', [$this->plan, $this->ration]))->assertForbidden();
    $this->assertDatabaseCount('rations', 1);
});

it('rejects a ration belonging to a different plan', function () {
    $otherPlan = PlanRationnement::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user)->post(route('plans.rations.duplicate', [$otherPlan, $this->ration]))->assertNotFound();
    $this->assertDatabaseCount('rations', 1);
});

it('requires authentication to duplicate a ration', function () {
    $this->post(route('plans.rations.duplicate', [$this->plan, $this->ration]))->assertRedirect(route('login'));
    $this->assertDatabaseCount('rations', 1);
});

it('rolls back the entire copy when an ingredient cannot be saved', function () {
    $dispatcher = Model::getEventDispatcher();
    Model::setEventDispatcher(clone $dispatcher);
    MelangeAliment::creating(function (): void {
        throw new RuntimeException('Ingredient copy failed');
    });

    try {
        expect(fn () => app(RationDuplicator::class)->duplicate($this->ration))->toThrow(RuntimeException::class, 'Ingredient copy failed');
    } finally {
        Model::setEventDispatcher($dispatcher);
    }

    $this->assertDatabaseCount('rations', 1);
    $this->assertDatabaseCount('ration_aliments', 2);
    $this->assertDatabaseCount('melanges', 3);
    $this->assertDatabaseCount('melange_aliments', 4);
    $this->assertDatabaseCount('aliments', 6);
});
