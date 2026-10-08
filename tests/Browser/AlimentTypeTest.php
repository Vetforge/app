<?php

use App\Models\Aliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Models\User;

it('offers the same canonical options in aliment and ration editors', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'inra' => '2018']);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'categorie_animal' => 'vache_laitiere']);
    $aliment = Aliment::factory()->create(['user_id' => $user->id, 'type' => 'fourrage', 'usage_aliment' => 2]);
    RationAliment::create(['ration_id' => $ration->id, 'aliment_id' => $aliment->id, 'quantite' => 2, 'is_volonte' => false, 'ordre' => 1]);
    $this->actingAs($user);

    $options = "Array.from(document.querySelectorAll('select[name=type] option')).filter(o => o.value).map(o => [o.value, o.textContent.trim()])";
    $expected = [['fourrage', 'fourrage'], ['concentre', 'concentre'], ['mineral', 'mineral']];
    $page = visit(route('aliments.create', absolute: false))->assertNoJavaScriptErrors();
    expect($page->script($options))->toBe($expected);
    $page->select('select[name=type]', 'concentre')->assertSelected('select[name=type]', 'concentre');

    $page = visit(route('plans.rations.composition', [$plan, $ration], absolute: false))
        ->assertNoJavaScriptErrors()->click('button[title="Éditer les valeurs"]')->assertSee('Modifier les valeurs');
    expect($page->script($options))->toBe($expected);
    $page->select('select[name=type]', 'mineral')->assertSelected('select[name=type]', 'mineral')->assertNoJavaScriptErrors();
});

it('persists a type change submitted from the aliment edit page', function (bool $complete, string $type) {
    $user = User::factory()->create();
    $aliment = Aliment::factory()->create([
        'user_id' => $user->id, 'code_inra' => null, 'type' => 'fourrage',
        'famille_botanique' => 'graminee', 'procede_technologique' => 'foin',
        'libelle0' => 'Foin test enregistrement', 'ms' => 85, 'mo' => null, 'mat' => null,
        'ufl' => 0.854392, 'ufv' => $complete ? 0.75 : null, 'pdi' => $complete ? 90 : null,
        'uel' => $complete ? 1 : null, 'uem' => $complete ? 1 : null, 'ueb' => $complete ? 1 : null,
    ]);
    $this->actingAs($user);
    visit(route('aliments.edit', $aliment, absolute: false))
        ->select('#type', $type)->click('Mettre à jour')->assertSee('Aliment enregistré.')->assertNoJavaScriptErrors();
    expect($aliment->fresh()->type)->toBe($type)
        ->and((float) $aliment->fresh()->ufl)->toBe(0.854392);
})->with([true, false])->with(['concentre', 'mineral']);

it('explains why a type change is refused for an aliment distributed at will', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    $aliment = Aliment::factory()->create(['user_id' => $user->id, 'code_inra' => null, 'type' => 'fourrage', 'usage_aliment' => 2]);
    RationAliment::create(['ration_id' => $ration->id, 'aliment_id' => $aliment->id, 'quantite' => 2, 'is_volonte' => true, 'ordre' => 1]);
    $this->actingAs($user);

    visit(route('aliments.edit', $aliment, absolute: false))
        ->select('#type', 'concentre')->click('Mettre à jour')
        ->assertSee("L'aliment n'a pas été enregistré.")
        ->assertSee('Un aliment distribué à volonté doit rester un fourrage.')
        ->assertDontSee('Aliment enregistré.')->assertNoJavaScriptErrors();
    expect($aliment->fresh()->type)->toBe('fourrage');
});
