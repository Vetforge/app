<?php

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\User;
use App\Support\SearchTerm;
use Inertia\Testing\AssertableInertia as Assert;

it('returns 404 when updating another users analysis', function () {
    $owner = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $owner->id]);
    $analysis = Analysis::factory()->create([
        'user_id' => $owner->id,
        'breeder_id' => $breeder->id,
        'module' => 'coproscopie-parasitaire',
    ]);

    $this->actingAs(User::factory()->create())
        ->put(route('analyses.update', $analysis), [
            'breeder_id' => $breeder->id,
            'payload' => ['species' => 'Bovin'],
        ])
        ->assertNotFound();
});

it('returns 404 when updating another users breeder', function () {
    $breeder = Breeder::factory()->create();

    $this->actingAs(User::factory()->create())
        ->put(route('breeders.update', $breeder), ['name' => 'Nouveau nom'])
        ->assertNotFound();
});

it('shares only whitelisted user fields with the frontend', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('breeders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.email', $user->email)
            ->missing('auth.user.normes_personnalisees')
            ->missing('auth.user.last_login_at')
            ->missing('auth.user.created_at')
            ->missing('auth.user.updated_at')
        );
});

it('applies rate limiting to expensive endpoints', function () {
    $middleware = fn (string $name): array => app('router')->getRoutes()->getByName($name)->gatherMiddleware();

    expect($middleware('aliments.pdf'))->toContain('throttle:10,1')
        ->and($middleware('plans.rations.pdf'))->toContain('throttle:10,1')
        ->and($middleware('analyses.pdf'))->toContain('throttle:10,1')
        ->and($middleware('breeders.import'))->toContain('throttle:5,1')
        ->and($middleware('admin.import.store'))->toContain('throttle:5,1')
        ->and($middleware('agrinir.calculer'))->toContain('throttle:30,1');
});

it('escapes like wildcards in search patterns', function () {
    expect(SearchTerm::likeContains('100%'))->toBe('%100\%%')
        ->and(SearchTerm::likeContains('a_b'))->toBe('%a\_b%')
        ->and(SearchTerm::likeContains('a\b'))->toBe('%a\\\\b%')
        ->and(SearchTerm::likeContains('gaec'))->toBe('%gaec%');
});

it('treats like wildcards literally when searching breeders', function () {
    $user = User::factory()->create();
    Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    Breeder::factory()->create(['user_id' => $user->id, 'name' => 'EARL 100% Herbe']);

    $this->actingAs($user)
        ->get(route('breeders.index', ['search' => '%']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('breeders.data', 1)
            ->where('breeders.data.0.name', 'EARL 100% Herbe')
        );
});
