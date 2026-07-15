<?php

use App\Enums\CategorieAnimal;
use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;
use App\Services\Equations2018\Besoin as Besoin2018;
use App\Services\Equations2018\CalculValeur;
use App\Services\RationCalculator;

use function Pest\Laravel\actingAs;

/**
 * Cas de non-régression pour les corrections issues de l'audit de conformité INRA 2018.
 */
function ration2018(CategorieAnimal $categorie, array $overrides = []): Ration
{
    $plan = PlanRationnement::factory()->create(['inra' => '2018']);

    return Ration::factory()->categorie($categorie)->create(array_merge([
        'plan_rationnement_id' => $plan->id,
        'poids_vif' => 650,
    ], $overrides))->load(['planRationnement', 'rationAliments.aliment', 'melanges.melangeAliments.aliment']);
}

// ─── ALI-01 : type d'aliment canonique ───────────────────────────────────────

test('aliment type is canonicalised to the tokens the engine expects (ALI-01)', function () {
    expect(Aliment::canonicalType('Concentré'))->toBe('Conc');
    expect(Aliment::canonicalType('concentre'))->toBe('Conc');
    expect(Aliment::canonicalType('Minéral'))->toBe('Mineral');
    expect(Aliment::canonicalType('FOURRAGES'))->toBe('Fourrage');

    $aliment = Aliment::factory()->create(['type' => 'Concentré']);
    expect($aliment->refresh()->type)->toBe('Conc');
});

// ─── ALI-03 : classification des familles de fourrage insensible à la casse ───

test('forage family classification is case and accent insensitive (ALI-03)', function () {
    $ration = ration2018(CategorieAnimal::VacheLaitiere);
    $base = ['type' => 'Fourrage', 'd_mo' => 70, 'mo' => 900, 'mat' => 150, 'niref' => 2, 'bpr' => 0];

    $csvCase = Aliment::factory()->make(array_merge($base, ['libelle0' => 'FOURRAGES VERTS']));
    $prettyCase = Aliment::factory()->make(array_merge($base, ['libelle0' => 'Fourrages verts']));
    $unknownFamily = Aliment::factory()->make(array_merge($base, ['libelle0' => 'DIVERS']));

    $deCsv = (new CalculValeur($ration, $csvCase))->calculerDEAliment();
    $dePretty = (new CalculValeur($ration, $prettyCase))->calculerDEAliment();
    $deConcentre = (new CalculValeur($ration, $unknownFamily))->calculerDEAliment();

    // La casse ne change plus l'équation retenue.
    expect($deCsv)->toEqualWithDelta($dePretty, 1e-9);
    // Un vrai fourrage n'est plus traité par l'équation « concentré » de repli.
    expect($deCsv)->not->toEqualWithDelta($deConcentre, 1e-6);
});

// ─── Section 10 : besoins minéraux routés par espèce ──────────────────────────

test('copper and sulphur requirements follow the caprine coefficients (section 10)', function () {
    $chevre = ration2018(CategorieAnimal::ChevreLaitiere, ['poids_vif' => 60]);
    $vache = ration2018(CategorieAnimal::VacheLaitiere);
    $forage = Aliment::factory()->create(['type' => 'Fourrage', 'ms' => 100]);
    foreach ([$chevre, $vache] as $ration) {
        $ration->rationAliments()->create([
            'aliment_id' => $forage->id, 'quantite' => 2.0, 'is_mb' => false, 'is_volonte' => false, 'ordre' => 1,
        ]);
        $ration->load('rationAliments.aliment');
    }

    // Cu : 15 mg/kg MSI (caprin) vs 10 mg/kg MSI (bovin) ; S : 2,2 vs 2,0.
    expect(Besoin2018::calculerBesoinCu($chevre))->toEqualWithDelta(15 * 2.0, 0.001);
    expect(Besoin2018::calculerBesoinCu($vache))->toEqualWithDelta(10 * 2.0, 0.001);
    expect(Besoin2018::calculerBesoinS($chevre))->toEqualWithDelta(2.2 * 2.0, 0.001);
    expect(Besoin2018::calculerBesoinS($vache))->toEqualWithDelta(2.0 * 2.0, 0.001);
});

// ─── BOV-A02 / OV-02 / CAP-09 : garde des termes de réserves ──────────────────

test('body-reserve (DRC) terms only apply to reproducing cows (BOV-A02/OV-02/CAP-09)', function () {
    $agneau = ration2018(CategorieAnimal::AgneauCroissance, ['poids_vif' => 30, 'ecart_variation_reserve' => 0.5]);
    $chevrette = ration2018(CategorieAnimal::ChevretteCroissance, ['poids_vif' => 40, 'ecart_variation_reserve' => 0.5]);

    foreach ([$agneau, $chevrette] as $ration) {
        expect(Besoin2018::calculerBesoinUF_DRC($ration))->toBe(0.0);
        expect(Besoin2018::calculerBesoinPDI_DRC($ration))->toBe(0.0);
    }
});

// ─── UI-01 / UI-08 : unités énergie/encombrement dans le contrat de résultat ──

test('result payload advertises the category-specific energy and encumbrance units (UI-01/UI-08)', function () {
    $engraissement = RationCalculator::calculer(
        ration2018(CategorieAnimal::BovinEngraissement, ['poids_vif' => 450, 'gmq' => 1300])
    );
    expect($engraissement['meta']['unite_fourragere'])->toBe('UFV');
    expect($engraissement['meta']['unite_encombrement'])->toBe('UEB');

    $laitiere = RationCalculator::calculer(ration2018(CategorieAnimal::VacheLaitiere));
    expect($laitiere['meta']['unite_fourragere'])->toBe('UFL');
    expect($laitiere['meta']['unite_encombrement'])->toBe('UEL');
});

// ─── FOR-02 : refus d'une catégorie inconnue ──────────────────────────────────

test('an unknown category is rejected instead of being silently mapped (FOR-02)', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);

    actingAs($user)
        ->post(route('plans.rations.store', $plan), [
            'nom' => 'Catégorie douteuse',
            'categorie_animal' => 'créature inconnue zzz',
        ])
        ->assertSessionHasErrors('categorie_animal');

    $this->assertDatabaseMissing('rations', ['nom' => 'Catégorie douteuse']);
});

// ─── FOR-05 : poids strictement positif ───────────────────────────────────────

test('a zero live weight is rejected (FOR-05)', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->vacheLaitiere()->create(['plan_rationnement_id' => $plan->id]);

    actingAs($user)
        ->put(route('plans.rations.description.update', [$plan, $ration]), [
            'nom' => $ration->nom,
            'categorie_animal' => 'vache_laitiere',
            'poids_vif' => 0,
        ])
        ->assertSessionHasErrors('poids_vif');
});

// ─── FOR-06 : précision décimale du lait ──────────────────────────────────────

test('lait_objectif keeps its decimal precision (FOR-06)', function () {
    $ration = ration2018(CategorieAnimal::VacheLaitiere, ['lait_objectif' => 2.5]);

    expect($ration->refresh()->lait_objectif)->toBe(2.5);
});
