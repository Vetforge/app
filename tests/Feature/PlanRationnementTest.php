<?php

use App\Models\Breeder;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('belongs to a user', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id]);

    expect($plan->user)->toBeInstanceOf(User::class);
});

it('has many rations', function () {
    $plan = PlanRationnement::factory()->create();
    Ration::factory()->count(3)->create(['plan_rationnement_id' => $plan->id]);

    expect($plan->rations)->toHaveCount(3);
});

it('lets the owner view their plan', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('plans.show', $plan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('plans/Show')
            ->where('plan.id', $plan->id)
            ->where('plan.user_id', $user->id)
        );
});

it('filters plans by plan and breeder fields', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create([
        'user_id' => $user->id,
        'name' => 'GAEC du Val',
        'city' => 'Aurillac',
        'herd_number' => 'FR15151515',
    ]);
    $matchingPlan = PlanRationnement::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'nom' => 'Plan hiver',
        'date' => '2026-05-12',
        'inra' => '2018',
    ]);

    PlanRationnement::factory()->create([
        'user_id' => $user->id,
        'nom' => 'Plan ete',
        'inra' => '2007',
    ]);

    $this->actingAs($user)
        ->get(route('plans.index', ['search' => 'val 2018 2026-05']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('plans/Index')
            ->where('filters.search', 'val 2018 2026-05')
            ->where('plans.total', 1)
            ->has('plans.data', 1)
            ->where('plans.data.0.id', $matchingPlan->id)
            ->where('plans.data.0.breeder.name', 'GAEC du Val')
        );
});

it('returns plans with scroll pagination metadata', function () {
    $user = User::factory()->create();

    PlanRationnement::factory()->count(26)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->get(route('plans.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('plans/Index')
            ->where('plans.total', 26)
            ->has('plans.data', 25)
        );

    $page = $response->viewData('page');

    expect(data_get($page, 'scrollProps.plans.currentPage'))->toBe(1)
        ->and(data_get($page, 'scrollProps.plans.nextPage'))->toBe(2);
});

it('stores a plan with a breeder owned by the authenticated user', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('plans.store'), [
            'nom' => 'Plan lactation',
            'date' => '2026-05-12',
            'inra' => '2018',
            'breeder_id' => $breeder->id,
        ])
        ->assertRedirect();

    expect(PlanRationnement::query()->where('user_id', $user->id)->where('breeder_id', $breeder->id)->exists())->toBeTrue();
});

it('rejects another users breeder when storing a plan', function () {
    $user = User::factory()->create();
    $foreignBreeder = Breeder::factory()->create();

    $this->actingAs($user)
        ->post(route('plans.store'), [
            'nom' => 'Plan lactation',
            'date' => '2026-05-12',
            'inra' => '2018',
            'breeder_id' => $foreignBreeder->id,
        ])
        ->assertSessionHasErrors('breeder_id');
});

it('forbids viewing another users plan', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->get(route('plans.show', $plan))
        ->assertForbidden();
});
