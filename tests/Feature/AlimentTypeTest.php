<?php

use App\Enums\TypeAliment;
use App\Models\Aliment;
use App\Models\Melange;
use App\Models\MelangeAliment;
use App\Models\PlanRationnement;
use App\Models\Ration;
use App\Models\RationAliment;
use App\Models\User;
use App\Services\Agrinir\ForageCalculator;
use App\Services\AlimentImporter;
use App\Services\Equations2007\Apport as Apport2007;
use App\Services\Equations2018\Apport as Apport2018;
use App\Services\RationCalculator;
use App\Services\RationHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function typeAlimentPayload(string $type): array
{
    return [
        'type' => $type,
        'libelle0' => 'Aliment test de type',
        'famille_botanique' => 'autre',
        'procede_technologique' => TypeAliment::tryFromLoose($type) === TypeAliment::Mineral ? 'mineral' : 'autre',
        'ms' => 85,
        'ufl' => 0.8,
        'ufv' => 0.7,
        'pdi' => 90,
        'uel' => 1,
        'uem' => 1,
        'ueb' => 1,
    ];
}

it('stores and updates canonical types through the aliment forms', function (string $alias, string $canonical) {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('aliments.store'), typeAlimentPayload($alias))->assertSessionHasNoErrors();
    $aliment = Aliment::query()->sole();
    expect($aliment->getRawOriginal('type'))->toBe($canonical);

    DB::table('aliments')->where('id', $aliment->id)->update(['type' => $alias]);
    $this->put(route('aliments.update', $aliment), typeAlimentPayload($alias))->assertSessionHasNoErrors();
    expect($aliment->fresh()->getRawOriginal('type'))->toBe($canonical);
})->with([
    ['Fourrage', 'fourrage'],
    ['Conc', 'concentre'],
    ['Concentré', 'concentre'],
    ['Minéral', 'mineral'],
]);

it('rejects invalid types in regular and admin forms', function (mixed $invalid) {
    $user = User::factory()->create(['is_admin' => true]);
    $aliment = Aliment::factory()->create(['user_id' => $user->id, 'code_inra' => null]);
    $payload = [...typeAlimentPayload('fourrage'), 'type' => $invalid];
    $this->actingAs($user);
    foreach (['aliments', 'admin.aliments'] as $prefix) {
        $this->post(route($prefix.'.store'), $payload)->assertSessionHasErrors('type');
        $this->put(route($prefix.'.update', $aliment), $payload)->assertSessionHasErrors('type');
    }
    expect(Aliment::query()->count())->toBe(1);
})->with(['melange', 'Perso', 'fourrage inconnu', 'non concentre', 'mineralise', null, '', [['Conc']]]);

it('normalizes admin creation and modification too', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->post(route('admin.aliments.store'), typeAlimentPayload('Conc'))->assertSessionHasNoErrors();
    $aliment = Aliment::query()->sole();
    expect($aliment->getRawOriginal('type'))->toBe('concentre');
    $this->put(route('admin.aliments.update', $aliment), typeAlimentPayload('Minéral'))->assertSessionHasNoErrors();
    expect($aliment->fresh()->getRawOriginal('type'))->toBe('mineral');
});

it('reads and copies legacy types in canonical form', function () {
    $aliment = Aliment::factory()->create(['type' => 'fourrage']);
    DB::table('aliments')->where('id', $aliment->id)->update(['type' => 'CONCENTRÉ']);
    $legacy = $aliment->fresh();
    expect($legacy->type)->toBe('concentre')->and($legacy->toArray()['type'])->toBe('concentre');
    $copy = $legacy->replicate();
    $copy->save();
    expect($copy->getRawOriginal('type'))->toBe('concentre');
    expect(fn () => $copy->type = 'concombre')->toThrow(InvalidArgumentException::class);
});

it('offers only canonical filter options and accepts old filter links', function () {
    $user = User::factory()->create();
    $aliment = Aliment::factory()->systemique()->create(['type' => 'concentre']);
    $this->actingAs($user)->get(route('aliments.index', ['type' => 'Conc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('types', ['fourrage', 'concentre', 'mineral'])
            ->where('filters.type', 'concentre')
            ->has('aliments.data', 1)
            ->where('aliments.data.0.id', $aliment->id)
            ->where('aliments.data.0.type', 'concentre'));
});

it('normalizes stored legacy types without guessing ambiguous categories', function () {
    $forage = Aliment::factory()->create();
    $concentrate = Aliment::factory()->create();
    $mineral = Aliment::factory()->create();
    $unknown = Aliment::factory()->create();
    foreach ([$forage->id => ' FOURRAGES ', $concentrate->id => 'Conc', $mineral->id => 'Minéral', $unknown->id => 'melange'] as $id => $type) {
        DB::table('aliments')->where('id', $id)->update(['type' => $type]);
    }
    $migration = require glob(database_path('migrations/*normalize_aliment_types.php'))[0];
    $migration->up();
    $migration->up();
    expect($forage->fresh()->getRawOriginal('type'))->toBe('fourrage')
        ->and($concentrate->fresh()->getRawOriginal('type'))->toBe('concentre')
        ->and($mineral->fresh()->getRawOriginal('type'))->toBe('mineral')
        ->and($unknown->fresh()->getRawOriginal('type'))->toBe('melange');
});

it('keeps identical ration calculations for legacy types including mixtures', function (string $inra) {
    $plan = PlanRationnement::factory()->create(['inra' => $inra]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'categorie_animal' => 'vacheLaitiere']);
    $melange = Melange::create(['ration_id' => $ration->id, 'quantite' => 4, 'is_mb' => false, 'is_volonte' => false, 'ordre' => 1]);
    $ids = [];
    foreach (['fourrage' => 'FOURRAGES', 'concentre' => 'Concentré', 'mineral' => 'Minéral'] as $type => $alias) {
        $aliment = Aliment::factory()->create([
            ...typeAlimentPayload($type), 'ufl2007' => 0.8, 'ufv2007' => 0.7, 'pdie2007' => 90, 'pdin2007' => 90, 'uel2007' => 1,
            'mo' => null, 'd_mo' => null, 'mat' => 140,
        ]);
        $ids[$aliment->id] = $alias;
        RationAliment::create(['ration_id' => $ration->id, 'aliment_id' => $aliment->id, 'quantite' => 2, 'is_mb' => false, 'is_volonte' => false, 'ordre' => 1]);
        MelangeAliment::create(['melange_id' => $melange->id, 'aliment_id' => $aliment->id, 'quantite' => 1, 'is_mb' => false, 'ordre' => 1]);
    }
    $expected = RationCalculator::calculer($ration->fresh());
    foreach ($ids as $id => $type) {
        DB::table('aliments')->where('id', $id)->update(['type' => $type]);
    }
    expect(RationCalculator::calculer($ration->fresh()))->toBe($expected);
    $ration = $ration->fresh();
    expect(Apport2018::calculerApportFourragesMS($ration))->toEqualWithDelta(10 / 3, 1e-6)
        ->and(Apport2018::calculerApportConcentresMS($ration))->toEqualWithDelta(10 / 3, 1e-6)
        ->and(Apport2007::calculerApportFourragesMS($ration))->toEqualWithDelta(10 / 3, 1e-6)
        ->and(Apport2007::calculerApportConcentresMS($ration))->toEqualWithDelta(10 / 3, 1e-6)
        ->and(RationHelper::calculerMoyennePondereeMelange($ration, $ration->melanges->first(), 'MAT', 'concentre'))->toBe(140.0)
        ->and(RationHelper::calculerMoyennePondereeMelange($ration, $ration->melanges->first(), 'MAT', 'Concentre'))->toBe(140.0);
})->with(['2007', '2018']);

it('blocks ambiguous legacy types while keeping the ration editable', function (string $inra, bool $inMelange) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['inra' => $inra, 'user_id' => $user->id]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id, 'categorie_animal' => 'vacheLaitiere']);
    $aliment = Aliment::factory()->create(['user_id' => $user->id]);
    DB::table('aliments')->where('id', $aliment->id)->update(['type' => 'melange']);
    if ($inMelange) {
        $melange = Melange::create(['ration_id' => $ration->id, 'quantite' => 2, 'is_volonte' => false, 'ordre' => 1]);
        MelangeAliment::create(['melange_id' => $melange->id, 'aliment_id' => $aliment->id, 'quantite' => 1, 'is_mb' => false, 'ordre' => 1]);
    } else {
        RationAliment::create(['ration_id' => $ration->id, 'aliment_id' => $aliment->id, 'quantite' => 2, 'is_volonte' => false, 'ordre' => 1]);
    }
    expect(fn () => RationCalculator::calculer($ration->fresh()))->toThrow(ValidationException::class);
    $this->actingAs($user)->get(route('plans.rations.composition', [$plan, $ration]))
        ->assertInertia(fn (Assert $page) => $page->where('resultats', null)->has('erreurs_types', 1));
    $this->post(route('plans.rations.aliments.add', [$plan, $ration]), ['aliment_id' => $aliment->id, 'quantite' => 2])->assertSessionHasErrors('aliment_id');
})->with(['2007', '2018'])->with([false, true]);

it('rejects unknown AgriNIR equation types at validation', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson(route('agrinir.calculer'), ['type' => 'concentre', 'inra' => '2018', 'params' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('type');
    $this->post(route('agrinir.sauvegarder'), ['nom' => 'Test', 'type' => 'inconnu', 'inra' => '2018', 'params' => [], 'valeurs' => []])
        ->assertSessionHasErrors('type');
});

it('uses the calculated AgriNIR category even when a reference has a different type', function (string $type, string $expected) {
    $user = User::factory()->create();
    $reference = Aliment::factory()->systemique()->create(['code_inra' => 'REF-TYPE', 'type' => 'mineral']);
    $params = ['humidite' => 30, 'proteine' => 12, 'ndf' => 40, 'adf' => 25, 'cendres' => 5, 'matiere_grasse' => 3, 'amidon' => 20];
    $this->actingAs($user)->post(route('agrinir.sauvegarder'), [
        'nom' => 'Aliment NIR', 'type' => $type, 'inra' => '2018', 'aliment_de_reference_id' => $reference->id,
        'params' => $params, 'valeurs' => ForageCalculator::calculer2018($type, $params),
    ])->assertSessionHasNoErrors();
    $aliment = Aliment::query()->where('user_id', $user->id)->sole();
    expect($aliment->getRawOriginal('type'))->toBe($expected)
        ->and($aliment->procede_technologique)->not->toBe('mineral')
        ->and($reference->fresh()->type)->toBe('mineral');
})->with([['maisE', 'fourrage'], ['luzerneD', 'concentre']]);

it('normalizes CSV aliases and rejects ambiguous import types', function () {
    $path = tempnam(sys_get_temp_dir(), 'aliment-types-');
    file_put_contents($path, "code_inra;type;libelle0;procede_technologique\nMIN001;Minéral;Sel;mineral\nMIN002;mineraux;Sel 2;mineral\nBAD001;mineralise;Invalide;mineral\n");
    try {
        expect(AlimentImporter::import($path))->toBe(['created' => 2, 'updated' => 0, 'errors' => 1])
            ->and(Aliment::query()->pluck('type')->all())->toBe(['mineral', 'mineral']);
    } finally {
        unlink($path);
    }
});

it('normalizes type changes from the ration editor and protects forage at will', function (bool $inMelange) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    $aliment = Aliment::factory()->create(['user_id' => $user->id, 'code_inra' => null, 'type' => 'fourrage', 'usage_aliment' => 2]);
    if ($inMelange) {
        $component = Melange::create(['ration_id' => $ration->id, 'quantite' => 2, 'is_volonte' => false, 'ordre' => 1]);
        $line = MelangeAliment::create(['melange_id' => $component->id, 'aliment_id' => $aliment->id, 'quantite' => 1, 'is_mb' => false, 'ordre' => 1]);
        $url = route('plans.rations.melanges.aliments.valeurs', [$plan, $ration, $component, $line]);
    } else {
        $component = RationAliment::create(['ration_id' => $ration->id, 'aliment_id' => $aliment->id, 'quantite' => 2, 'is_volonte' => false, 'ordre' => 1]);
        $url = route('plans.rations.aliments.valeurs', [$plan, $ration, $component]);
    }
    $this->actingAs($user)->patch($url, typeAlimentPayload('Conc'))->assertSessionHasNoErrors();
    expect($aliment->fresh()->type)->toBe('concentre');
    $aliment->refresh()->update(['type' => 'fourrage']);
    $component->update(['is_volonte' => true]);
    $this->patch($url, typeAlimentPayload('Conc'))->assertSessionHasErrors('type');
    expect($aliment->fresh()->type)->toBe('fourrage');
})->with([false, true]);

it('accepts legacy forage at will but refuses other or untyped components in a mixture', function (mixed $rawType, bool $allowed) {
    $user = User::factory()->create();
    $plan = PlanRationnement::factory()->create(['user_id' => $user->id]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    $aliment = Aliment::factory()->systemique()->create(['type' => 'fourrage']);
    DB::table('aliments')->where('id', $aliment->id)->update(['type' => $rawType]);
    $melange = Melange::create(['ration_id' => $ration->id, 'quantite' => 2, 'is_volonte' => false, 'ordre' => 1]);
    MelangeAliment::create(['melange_id' => $melange->id, 'aliment_id' => $aliment->id, 'quantite' => 1, 'is_mb' => false, 'ordre' => 1]);
    $response = $this->actingAs($user)->put(route('plans.rations.melanges.update', [$plan, $ration, $melange]), ['is_volonte' => true]);
    if ($allowed) {
        $response->assertSessionHasNoErrors();
    } else {
        $response->assertSessionHasErrors('is_volonte');
    }
    expect($melange->fresh()->is_volonte)->toBe($allowed);
})->with([['Fourrage', true], ['fourrage', true], ['Conc', false], ['mineral', false], [null, false]]);

it('refuses a legacy concentrate marked as forage at will in both engines', function (string $inra) {
    $plan = PlanRationnement::factory()->create(['inra' => $inra]);
    $ration = Ration::factory()->create(['plan_rationnement_id' => $plan->id]);
    $aliment = Aliment::factory()->create(['type' => 'concentre']);
    RationAliment::create(['ration_id' => $ration->id, 'aliment_id' => $aliment->id, 'quantite' => 2, 'is_volonte' => true, 'ordre' => 1]);
    expect(fn () => RationCalculator::calculer($ration))->toThrow(ValidationException::class);
})->with(['2007', '2018']);

it('allows correcting a legacy type without changing incomplete nutritional values', function (string $type) {
    $user = User::factory()->create();
    $aliment = Aliment::factory()->create([
        'user_id' => $user->id, 'code_inra' => null, 'type' => 'fourrage',
        'libelle0' => 'Aliment historique', 'libelle1' => 'Libellé conservé',
        'famille_botanique' => null, 'procede_technologique' => null,
        'ms' => 87.453, 'ufl' => 0.854392, 'ufv' => null, 'pdi' => null,
    ]);
    DB::table('aliments')->where('id', $aliment->id)->update(['type' => 'melange']);
    $this->actingAs($user)->put(route('aliments.update', $aliment), [
        'type' => $type, 'libelle0' => $aliment->libelle0,
    ])->assertSessionHasNoErrors();
    expect($aliment->fresh()->type)->toBe($type)
        ->and($aliment->fresh()->libelle1)->toBe('Libellé conservé')
        ->and((float) $aliment->fresh()->ms)->toBe($type === 'mineral' ? 100.0 : 87.453)
        ->and((float) $aliment->fresh()->ufl)->toBe(0.854392)
        ->and($aliment->fresh()->ufv)->toBeNull();
    if ($type === 'mineral') {
        expect($aliment->fresh()->procede_technologique)->toBe('mineral');
    }
})->with(['fourrage', 'concentre', 'mineral']);

it('still rejects an incomplete nutritional modification', function () {
    $user = User::factory()->create();
    $aliment = Aliment::factory()->create([
        'user_id' => $user->id, 'code_inra' => null, 'type' => 'fourrage',
        'ms' => 85, 'ufl' => 0.8, 'ufv' => null, 'pdi' => null,
    ]);
    $this->actingAs($user)->put(route('aliments.update', $aliment), [
        'type' => 'concentre', 'libelle0' => $aliment->libelle0, 'ufl' => 0.9,
    ])->assertSessionHasErrors('ufl');
    expect($aliment->fresh()->type)->toBe('fourrage')
        ->and((float) $aliment->fresh()->ufl)->toBe(0.8);
});

it('validates a partial nutritional edit using the stored values too', function () {
    $user = User::factory()->create();
    $aliment = Aliment::factory()->create([
        ...typeAlimentPayload('fourrage'), 'user_id' => $user->id, 'code_inra' => null,
    ]);
    $this->actingAs($user)->put(route('aliments.update', $aliment), [
        'type' => 'fourrage', 'libelle0' => $aliment->libelle0, 'ufl' => 0.9,
    ])->assertSessionHasNoErrors();
    expect((float) $aliment->fresh()->ufl)->toBe(0.9)
        ->and((float) $aliment->fresh()->pdi)->toBe(90.0);
});
