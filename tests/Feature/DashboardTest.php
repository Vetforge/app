<?php

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\PlanRationnement;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

test('recent analysis elements expose their module for icon mapping', function () {
    $user = User::factory()->create();
    $analysis = Analysis::factory()->create([
        'user_id' => $user->id,
        'module' => 'gaz-du-sang',
        'animal_nom' => 'Jasmine',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('recent_elements.0.type', 'analysis')
            ->where('recent_elements.0.id', $analysis->id)
            ->where('recent_elements.0.module', 'gaz-du-sang')
            ->etc()
        );
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

test('search exposes analysis module for icon mapping', function () {
    $user = User::factory()->create();
    $analysis = Analysis::factory()->create([
        'user_id' => $user->id,
        'module' => 'coproscopie-parasitaire',
        'animal_nom' => 'Jasmine',
    ]);

    $this->actingAs($user);

    $response = $this->getJson(route('dashboard.search', ['q' => 'Jasmine']))
        ->assertOk();

    $result = collect($response->json())->firstWhere('id', $analysis->id);

    expect($result)
        ->not->toBeNull()
        ->and($result['type'])->toBe('analysis')
        ->and($result['module'])->toBe('coproscopie-parasitaire');
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
