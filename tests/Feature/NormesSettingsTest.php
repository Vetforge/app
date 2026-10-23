<?php

use App\Enums\CategorieAnimal;
use App\Models\User;
use App\Support\RationNormes;
use App\Support\VeterinaryModules;
use Inertia\Testing\AssertableInertia as Assert;

it('stores and reloads custom bounds that have no defaults for the current account', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $normes = RationNormes::defaults();
    $normes['bpr'] = ['min' => 1.25, 'max' => 4.75];
    $normes['ph_ruminal'] = ['min' => 6.4, 'max' => null];

    $this->actingAs($user)->from(route('normes.edit'))
        ->patch(route('normes.update'), ['normes' => $normes, 'user_id' => $other->id])
        ->assertSessionHasNoErrors()->assertRedirect(route('normes.edit'));

    expect($user->fresh()->normes_personnalisees['categories']['vache_laitiere'])->toMatchArray([
        'bpr' => ['min' => 1.25, 'max' => 4.75],
        'ph_ruminal' => ['min' => 6.4, 'max' => null],
    ])->and($other->fresh()->normes_personnalisees)->toBeNull();

    $this->get(route('normes.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('normes.active.bpr.min', 1.25)->where('normes.active.bpr.max', 4.75)
        ->where('normes.active.ph_ruminal.min', 6.4)
        ->where('normes.editable.1.supports_min', true)->where('normes.editable.1.supports_max', true)
    );
});

it('rejects inverted custom ranges even when defaults are empty', function (string $key) {
    $user = User::factory()->create();
    $normes = RationNormes::defaults();
    $normes[$key] = ['min' => 5, 'max' => 4];

    $this->actingAs($user)->patch(route('normes.update'), ['normes' => $normes])
        ->assertSessionHasErrors("normes.$key.max");
    expect($user->fresh()->normes_personnalisees)->toBeNull();
})->with(['bpr', 'be', 'amid_ru', 'pco_percent', 'ndf_total', 'ira']);

it('accepts optional bounds and equal bounds', function (?float $min, ?float $max) {
    $user = User::factory()->create();
    $normes = RationNormes::defaults();
    $normes['bpr'] = ['min' => $min, 'max' => $max];

    $this->actingAs($user)->from(route('normes.edit'))
        ->patch(route('normes.update'), ['normes' => $normes])
        ->assertSessionHasNoErrors()->assertRedirect(route('normes.edit'));
    $this->get(route('normes.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('normes.active.bpr.min', $min)->where('normes.active.bpr.max', $max)
    );
})->with([[null, 4.5], [1.5, null], [1.5, 1.5], [null, null]]);

it('rejects non numeric custom bounds without clearing stored preferences', function (string $bound) {
    $user = User::factory()->create(['normes_personnalisees' => ['bpr' => ['min' => 1, 'max' => 4]]]);
    $normes = RationNormes::active($user->normes_personnalisees);
    $normes['bpr'][$bound] = 'invalide';

    $this->actingAs($user)->patch(route('normes.update'), ['normes' => $normes])
        ->assertSessionHasErrors("normes.bpr.$bound");
    expect($user->fresh()->normes_personnalisees['bpr'])->toBe(['min' => 1, 'max' => 4]);
})->with(['min', 'max']);

it('can remove a default threshold explicitly and reset the selected category', function () {
    $user = User::factory()->create(['normes_personnalisees' => ['categories' => ['vache_laitiere' => ['bpr' => ['min' => 1, 'max' => 4]]]]]);
    $normes = RationNormes::active($user->normes_personnalisees);
    $normes['bil_ufl']['min'] = null;

    $this->actingAs($user)->patch(route('normes.update'), ['normes' => $normes])->assertSessionHasNoErrors();
    $this->get(route('normes.edit'))->assertInertia(fn (Assert $page) => $page->where('normes.active.bil_ufl.min', null));

    $this->patch(route('normes.update'), ['normes' => RationNormes::defaults()])->assertSessionHasNoErrors();
    expect($user->fresh()->normes_personnalisees)->toBeNull();
});

it('integrates ration into module settings without adding a veterinary analysis module', function () {
    $user = User::factory()->create(['normes_personnalisees' => ['bpr' => ['min' => 1, 'max' => 4]]]);

    $this->actingAs($user)->get(route('ration-settings.edit'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/ModuleSettings')
        ->where('module.slug', 'ration')->where('modules.0.slug', 'ration')
        ->where('normes.active.bpr.min', 1)->has('normes.editable', count(RationNormes::definitions()))
        );
    $this->get(route('module-settings.edit', ['module' => 'gaz-du-sang']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('modules.0.slug', 'ration'));
    expect(VeterinaryModules::slugs())->not->toContain('ration');
});

it('stores all bounds through the ration module and preserves preferences omitted from a partial update', function () {
    $user = User::factory()->create(['normes_personnalisees' => ['bpr' => ['min' => 1, 'max' => 4]]]);
    $other = User::factory()->create();
    $this->actingAs($user)->from(route('ration-settings.edit'))
        ->patch(route('ration-settings.update'), ['normes' => [
            'ph_ruminal' => ['min' => 6, 'max' => 6.8],
            'cb_par_kg_ms' => ['min' => null, 'max' => 250],
            'couverture_caabs' => ['min' => 80, 'max' => 130],
            'ira_be' => ['min' => 150, 'max' => 225],
        ], 'user_id' => $other->id])
        ->assertSessionHasNoErrors()->assertRedirect(route('ration-settings.edit'));

    expect($user->fresh()->normes_personnalisees['categories']['vache_laitiere'])->toMatchArray([
        'bpr' => ['min' => 1, 'max' => 4], 'ph_ruminal' => ['min' => 6, 'max' => 6.8],
        'cb_par_kg_ms' => ['min' => null, 'max' => 250], 'ira_be' => ['min' => 150, 'max' => 225],
    ])->and($other->fresh()->normes_personnalisees)->toBeNull();
});

it('validates every newly available upper bound', function (string $key) {
    $user = User::factory()->create();
    $this->actingAs($user)->patch(route('ration-settings.update'), ['normes' => [$key => ['min' => 5, 'max' => 4]]])
        ->assertSessionHasErrors('normes.'.$key.'.max');
    expect($user->fresh()->normes_personnalisees)->toBeNull();
})->with(['ph_ruminal', 'eff_pdi', 'cb_par_kg_ms', 'bil_ufl', 'couverture_uf', 'ira_be', 'adf_par_kg_ms']);

it('resets ration preferences only for the authenticated user', function () {
    $user = User::factory()->create(['normes_personnalisees' => ['bpr' => ['min' => 1, 'max' => 4]]]);
    $other = User::factory()->create(['normes_personnalisees' => ['bpr' => ['min' => 2, 'max' => 5]]]);
    $this->actingAs($user)->delete(route('ration-settings.destroy'))->assertRedirect();
    expect(RationNormes::active($user->fresh()->normes_personnalisees)['bpr'])->toBe(['min' => null, 'max' => null])
        ->and(RationNormes::active($user->fresh()->normes_personnalisees, CategorieAnimal::BrebisLaitiere)['bpr'])->toBe(['min' => 1.0, 'max' => 4.0])
        ->and($other->fresh()->normes_personnalisees['bpr'])->toBe(['min' => 2, 'max' => 5]);
});

it('requires authentication for ration settings', function () {
    $this->get(route('ration-settings.edit'))->assertRedirect(route('login'));
    $this->patch(route('ration-settings.update'))->assertRedirect(route('login'));
    $this->delete(route('ration-settings.destroy'))->assertRedirect(route('login'));
});

it('validates partial bounds against the saved range and keeps omitted bounds', function () {
    $user = User::factory()->create(['normes_personnalisees' => ['bpr' => ['min' => 1, 'max' => 4]]]);
    $this->actingAs($user)->patch(route('ration-settings.update'), ['normes' => ['bpr' => ['min' => 5]]])
        ->assertSessionHasErrors('normes.bpr.max');
    $this->patch(route('ration-settings.update'), ['normes' => ['bpr' => ['min' => 2]]])
        ->assertSessionHasNoErrors();
    expect($user->fresh()->normes_personnalisees['categories']['vache_laitiere']['bpr'])->toBe(['min' => 2, 'max' => 4]);
});

it('isolates each animal category and account when updating and resetting bounds', function (CategorieAnimal $categorie) {
    $user = User::factory()->create();
    $other = User::factory()->create(['normes_personnalisees' => ['categories' => [
        $categorie->value => ['bpr' => ['min' => 10, 'max' => 20]],
    ]]]);
    $url = route('ration-settings.edit', ['categorie_animal' => $categorie->value]);

    $this->actingAs($user)->from($url)->patch(route('ration-settings.update'), [
        'categorie_animal' => $categorie->value,
        'normes' => ['bpr' => ['min' => 1.25, 'max' => 4.75]],
        'user_id' => $other->id,
    ])->assertSessionHasNoErrors()->assertRedirect($url);

    foreach (CategorieAnimal::cases() as $category) {
        $expected = $category === $categorie ? 1.25 : null;
        $this->get(route('ration-settings.edit', ['categorie_animal' => $category->value]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('normes.categorie_animal', $category->value)
                ->where('normes.active.bpr.min', $expected)
                ->where('normes.profiles.'.$categorie->value.'.bpr.min', 1.25)
                ->has('normes.categories', 3)
            );
    }

    $this->delete(route('ration-settings.destroy'), ['categorie_animal' => $categorie->value, 'user_id' => $other->id])
        ->assertSessionHasNoErrors();
    expect($user->fresh()->normes_personnalisees)->toBeNull();
    $this->actingAs($other)->get($url)->assertInertia(fn (Assert $page) => $page
        ->where('normes.active.bpr.min', 10)->where('normes.active.bpr.max', 20)
    );
})->with(CategorieAnimal::cases());

it('validates partial ranges against the selected category rather than the dairy cow profile', function () {
    $user = User::factory()->create(['normes_personnalisees' => ['categories' => [
        'vache_laitiere' => ['bpr' => ['min' => 1, 'max' => 4]],
        'chevre_laitiere' => ['bpr' => ['min' => 10, 'max' => 20]],
    ]]]);
    $this->actingAs($user)->patch(route('ration-settings.update'), [
        'categorie_animal' => 'chevre_laitiere', 'normes' => ['bpr' => ['min' => 15]],
    ])->assertSessionHasNoErrors();
    $this->patch(route('ration-settings.update'), [
        'categorie_animal' => 'chevre_laitiere', 'normes' => ['bpr' => ['max' => 14]],
    ])->assertSessionHasErrors('normes.bpr.max');

    expect($user->fresh()->normes_personnalisees['categories'])->toMatchArray([
        'vache_laitiere' => ['bpr' => ['min' => 1, 'max' => 4]],
        'chevre_laitiere' => ['bpr' => ['min' => 15, 'max' => 20]],
    ]);
    $this->delete(route('ration-settings.destroy'), ['categorie_animal' => 'chevre_laitiere'])->assertSessionHasNoErrors();
    expect($user->fresh()->normes_personnalisees['categories'])->toBe([
        'vache_laitiere' => ['bpr' => ['min' => 1, 'max' => 4]],
    ]);
});

it('rejects unknown or malformed categories without changing preferences', function (mixed $category) {
    $user = User::factory()->create();
    $this->actingAs($user)->patch(route('ration-settings.update'), [
        'categorie_animal' => $category, 'normes' => ['bpr' => ['min' => 1]],
    ])->assertSessionHasErrors('categorie_animal');
    $this->delete(route('ration-settings.destroy'), ['categorie_animal' => $category])
        ->assertSessionHasErrors('categorie_animal');
    expect($user->fresh()->normes_personnalisees)->toBeNull();
})->with(['unknown' => ['inconnue'], 'empty' => [null], 'array' => [['vache_laitiere']]]);
