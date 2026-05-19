<?php

use App\Models\Breeder;
use App\Models\PlanRationnement;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('legacy dashboard url redirects to the root dashboard url', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/dashboard')
        ->assertRedirect(route('dashboard'));
});

test('search returns empty for guests', function () {
    $this->getJson(route('dashboard.search', ['q' => 'test']))
        ->assertUnauthorized();
});

test('search returns empty for queries under 2 characters', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->getJson(route('dashboard.search', ['q' => 'a']))
        ->assertOk()
        ->assertJson([]);
});

test('search finds plan by name', function () {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create([
        'user_id' => $user->id,
        'nom' => 'Ration Vaches Laitières',
    ]);

    $this->actingAs($user);

    $response = $this->getJson(route('dashboard.search', ['q' => 'Vaches']))
        ->assertOk();

    $results = collect($response->json());
    expect($results->contains(fn ($item) => $item['type'] === 'plan' && $item['id'] === $plan->id))->toBeTrue();
});

test('search finds plan by breeder name', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create([
        'user_id' => $user->id,
        'name' => 'GAEC Alaux',
    ]);
    $plan = PlanRationnement::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'nom' => 'Plan de ration hiver',
    ]);

    $this->actingAs($user);

    $response = $this->getJson(route('dashboard.search', ['q' => 'alaux']))
        ->assertOk();

    $results = collect($response->json());
    expect($results->contains(fn ($item) => $item['type'] === 'plan' && $item['id'] === $plan->id))->toBeTrue();
});

test('search does not return plans from other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $plan = PlanRationnement::factory()->create([
        'user_id' => $other->id,
        'nom' => 'Ration secrète',
    ]);

    $this->actingAs($user);

    $response = $this->getJson(route('dashboard.search', ['q' => 'secrète']))
        ->assertOk();

    $results = collect($response->json());
    expect($results->contains(fn ($item) => $item['type'] === 'plan' && $item['id'] === $plan->id))->toBeFalse();
});
