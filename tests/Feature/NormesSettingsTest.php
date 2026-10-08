<?php

use App\Models\User;
use App\Support\RationNormes;
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

    expect($user->fresh()->normes_personnalisees)->toMatchArray([
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

it('can remove a default threshold explicitly and reset all preferences', function () {
    $user = User::factory()->create(['normes_personnalisees' => ['bpr' => ['min' => 1, 'max' => 4]]]);
    $normes = RationNormes::active($user->normes_personnalisees);
    $normes['bil_ufl']['min'] = null;

    $this->actingAs($user)->patch(route('normes.update'), ['normes' => $normes])->assertSessionHasNoErrors();
    $this->get(route('normes.edit'))->assertInertia(fn (Assert $page) => $page->where('normes.active.bil_ufl.min', null));

    $this->patch(route('normes.update'), ['normes' => RationNormes::defaults()])->assertSessionHasNoErrors();
    expect($user->fresh()->normes_personnalisees)->toBeNull();
});
