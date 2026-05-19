<?php

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

it('lists only breeders owned by the authenticated user', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id, 'name' => 'GAEC du Val']);
    Breeder::factory()->create(['name' => 'EARL Externe']);

    $this->actingAs($user)
        ->get(route('breeders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('breeders/Index')
            ->has('breeders.data', 1)
            ->where('breeders.data.0.id', $breeder->id)
            ->where('breeders.data.0.name', 'GAEC du Val')
        );
});

it('creates breeders with names unique per user only', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Breeder::factory()->create(['user_id' => $otherUser->id, 'name' => 'GAEC du Val']);

    $this->actingAs($user)
        ->post(route('breeders.store'), [
            'name' => 'GAEC du Val',
            'city' => 'Aurillac',
            'email' => 'contact@example.test',
        ])
        ->assertRedirect();

    expect(Breeder::query()->where('user_id', $user->id)->where('name', 'GAEC du Val')->exists())->toBeTrue();

    $this->actingAs($user)
        ->post(route('breeders.store'), ['name' => 'GAEC du Val'])
        ->assertSessionHasErrors('name');
});

it('does not allow opening another users breeder', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create();

    $this->actingAs($user)
        ->get(route('breeders.edit', $breeder))
        ->assertNotFound();
});

it('imports csv breeders and reports invalid rows', function () {
    $user = User::factory()->create();
    Breeder::factory()->create([
        'user_id' => $user->id,
        'name' => 'GAEC Existant',
        'city' => 'Ancienne ville',
    ]);

    $csv = implode("\n", [
        'nom;adresse;code_postal;ville;telephone;email;numero_cheptel;notes',
        'GAEC Nouveau;1 rue des pres;15000;Aurillac;0102030405;nouveau@example.test;FR123;Note',
        'GAEC Existant;2 rue;12000;Rodez;;existant@example.test;FR456;',
        ';sans nom;12000;Ville;;;',
        'GAEC Email;3 rue;12000;Ville;;not-an-email;;',
    ]);

    $file = UploadedFile::fake()->createWithContent('eleveurs.csv', $csv);

    $this->actingAs($user)
        ->post(route('breeders.import'), ['file' => $file])
        ->assertRedirect(route('breeders.index'))
        ->assertSessionHas('import_errors');

    expect(Breeder::query()->where('user_id', $user->id)->where('name', 'GAEC Nouveau')->exists())->toBeTrue()
        ->and(Breeder::query()->where('user_id', $user->id)->where('name', 'GAEC Existant')->value('city'))->toBe('Rodez')
        ->and(Breeder::query()->where('user_id', $user->id)->where('name', 'GAEC Email')->exists())->toBeFalse();
});

it('creates a breeder from the quick creation endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('breeders.quick-store'), [
            'name' => 'GAEC Rapide',
            'city' => 'Aurillac',
            'herd_number' => 'FR123',
        ])
        ->assertCreated()
        ->assertJsonPath('breeder.name', 'GAEC Rapide')
        ->assertJsonPath('breeder.city', 'Aurillac')
        ->assertJsonPath('breeder.herd_number', 'FR123');

    expect(Breeder::query()->where('user_id', $user->id)->where('name', 'GAEC Rapide')->exists())->toBeTrue();
});

it('downloads the breeder import example csv', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('breeders.import-example'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())
        ->toContain('nom;adresse;code_postal;ville;telephone;email;numero_cheptel;notes')
        ->toContain('GAEC du Val');
});

it('deletes a breeder and its analyses for that user', function () {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $analysis = Analysis::factory()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
    ]);

    $this->actingAs($user)
        ->delete(route('breeders.destroy', $breeder))
        ->assertRedirect(route('breeders.index'));

    expect(Breeder::query()->find($breeder->id))->toBeNull()
        ->and(Analysis::query()->find($analysis->id))->toBeNull();
});
