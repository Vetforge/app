<?php

use App\Models\Aliment;
use App\Models\Breeder;
use App\Models\Melange;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function breederAlimentPayload(array $overrides = []): array
{
    return array_replace([
        'libelle0' => 'Aliment ferme',
        'type' => 'Mineral',
        'procede_technologique' => 'mineral',
    ], $overrides);
}

it('creates an aliment with an optional breeder and can clear its attribution', function (?bool $assigned) {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $payload = breederAlimentPayload($assigned ? ['breeder_id' => $breeder->id] : []);

    $this->actingAs($user)->post(route('aliments.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $aliment = Aliment::where('user_id', $user->id)->sole();
    expect($aliment->breeder_id)->toBe($assigned ? $breeder->id : null);

    $this->put(route('aliments.update', $aliment), breederAlimentPayload(['breeder_id' => $breeder->id]))->assertSessionHasNoErrors();
    expect($aliment->fresh()->breeder_id)->toBe($breeder->id);
    $this->put(route('aliments.update', $aliment), breederAlimentPayload(['breeder_id' => null]))->assertSessionHasNoErrors();
    expect($aliment->fresh()->breeder_id)->toBeNull();
})->with([true, false]);

it('rejects an unknown or another users breeder for an aliment', function (bool $unknown) {
    $user = User::factory()->create();
    $id = $unknown ? 999999 : Breeder::factory()->create()->id;
    $this->actingAs($user)->post(route('aliments.store'), breederAlimentPayload(['breeder_id' => $id]))
        ->assertSessionHasErrors('breeder_id');
    expect(Aliment::where('user_id', $user->id)->count())->toBe(0);
})->with([true, false]);

it('rejects breeder attribution to protected inra aliments even for admins', function (string $updateRoute) {
    $admin = User::factory()->create(['is_admin' => true]);
    $breeder = Breeder::factory()->create(['user_id' => $admin->id]);
    $aliment = Aliment::factory()->systemique()->create(['code_inra' => '1234']);
    $this->actingAs($admin)->put(route($updateRoute, $aliment), breederAlimentPayload(['breeder_id' => $breeder->id]))
        ->assertSessionHasErrors('breeder_id');
    expect($aliment->fresh()->breeder_id)->toBeNull();
})->with(['aliments.update', 'admin.aliments.update']);

it('supplies only owned breeders in aliment and agrinir forms', function (string $route) {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    Breeder::factory()->create();
    $this->actingAs($user)->get(route($route))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('breeders', 1)->where('breeders.0.id', $breeder->id)->has('quickBreederStoreUrl'));
})->with(['aliments.create', 'agrinir.show']);

it('offers the plans breeder aliments and anonymous aliments in the ration', function (bool $assigned) {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $other = Breeder::factory()->create(['user_id' => $user->id]);
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'breeder_id' => $assigned ? $breeder->id : null]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    $anonymous = Aliment::factory()->create(['user_id' => $user->id, 'usage_aliment' => null]);
    $reference = Aliment::factory()->systemique()->create(['usage_aliment' => null]);
    $own = Aliment::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'code_inra' => null, 'usage_aliment' => null]);
    Aliment::factory()->create(['user_id' => $user->id, 'breeder_id' => $other->id, 'code_inra' => null, 'usage_aliment' => null]);
    Aliment::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id, 'usage_aliment' => 2]);
    Aliment::factory()->create(['usage_aliment' => null]);

    $expected = [$anonymous->id, $reference->id, ...($assigned ? [$own->id] : [])];
    $this->actingAs($user)->get(route('plans.rations.composition', [$plan, $ration]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('aliments_disponibles', fn ($aliments) => collect($aliments)->pluck('id')->sort()->values()->all() === collect($expected)->sort()->values()->all()));
})->with([true, false]);

it('rejects another breeders aliment when added directly or to a mixture', function (bool $mixture) {
    $user = User::factory()->create();
    $breeder = Breeder::factory()->create(['user_id' => $user->id]);
    $other = Breeder::factory()->create(['user_id' => $user->id]);
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id, 'breeder_id' => $breeder->id]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    $aliment = Aliment::factory()->create(['user_id' => $user->id, 'breeder_id' => $other->id, 'code_inra' => null]);
    $args = [$plan, $ration];
    if ($mixture) {
        $args[] = Melange::create(['ration_id' => $ration->id, 'nom' => 'Mélange', 'ordre' => 1]);
    }
    $route = $mixture ? 'plans.rations.melanges.aliments.add' : 'plans.rations.aliments.add';
    $this->actingAs($user)->post(route($route, $args), ['aliment_id' => $aliment->id, 'quantite' => 2])->assertSessionHasErrors('aliment_id');
    expect(Aliment::count())->toBe(1);
})->with([true, false]);
