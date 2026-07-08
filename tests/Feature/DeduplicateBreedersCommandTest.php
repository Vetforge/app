<?php

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\PlanRationnement;
use App\Models\User;

/**
 * Déduplication des éleveurs : fusion des doublons (même cabinet + même numéro de cheptel)
 * sur l'éleveur le plus actif, sans jamais fusionner entre cabinets différents.
 */
function breederWith(User $user, ?string $herd, int $analyses = 0, int $plans = 0): Breeder
{
    $breeder = Breeder::factory()->create([
        'user_id' => $user->id,
        'herd_number' => $herd,
    ]);

    Analysis::factory()->count($analyses)->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
    ]);

    PlanRationnement::factory()->count($plans)->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
    ]);

    return $breeder;
}

test('merges duplicate breeders of one cabinet onto the breeder with the most analyses', function () {
    $user = User::factory()->create();
    $winner = breederWith($user, 'FR001', analyses: 3, plans: 0);
    $loserA = breederWith($user, 'FR001', analyses: 1, plans: 2);
    $loserB = breederWith($user, 'FR001', analyses: 0, plans: 1);

    $this->artisan('breeders:deduplicate', ['--force' => true])->assertOk();

    expect(Breeder::where('user_id', $user->id)->count())->toBe(1);
    expect(Breeder::find($winner->id))->not->toBeNull();
    expect(Breeder::find($loserA->id))->toBeNull();
    expect(Breeder::find($loserB->id))->toBeNull();

    // Analyses et plans repointés sur la cible, sans perte (pas de cascade destructrice).
    expect(Analysis::where('breeder_id', $winner->id)->count())->toBe(4);
    expect(PlanRationnement::where('breeder_id', $winner->id)->count())->toBe(3);
    expect(Analysis::count())->toBe(4);
});

test('never merges breeders sharing a herd number but belonging to different cabinets', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    $b1 = breederWith($u1, 'FR777', analyses: 1);
    $b2 = breederWith($u2, 'FR777', analyses: 5);

    $this->artisan('breeders:deduplicate', ['--force' => true])->assertOk();

    expect(Breeder::find($b1->id))->not->toBeNull();
    expect(Breeder::find($b2->id))->not->toBeNull();
});

test('matches herd numbers after trimming spaces and uppercasing, and never merges blank ones', function () {
    $user = User::factory()->create();
    $a = breederWith($user, 'fr 123 456', analyses: 2);
    $b = breederWith($user, 'FR123456', analyses: 1);
    $nul = breederWith($user, null);
    $vide = breederWith($user, '');
    $espaces = breederWith($user, '   ');

    $this->artisan('breeders:deduplicate', ['--force' => true])->assertOk();

    // a + b fusionnés → 1 ; les trois cheptels vides restent intacts → 3.
    expect(Breeder::where('user_id', $user->id)->count())->toBe(4);
    expect(Breeder::find($a->id))->not->toBeNull();
    expect(Breeder::find($b->id))->toBeNull();
    expect(Breeder::find($nul->id))->not->toBeNull();
    expect(Breeder::find($vide->id))->not->toBeNull();
    expect(Breeder::find($espaces->id))->not->toBeNull();
});

test('breaks a tie on analyses by keeping the breeder with the most plans', function () {
    $user = User::factory()->create();
    $fewPlans = breederWith($user, 'FR700', analyses: 2, plans: 1);
    $manyPlans = breederWith($user, 'FR700', analyses: 2, plans: 3);

    $this->artisan('breeders:deduplicate', ['--force' => true])->assertOk();

    expect(Breeder::find($manyPlans->id))->not->toBeNull();
    expect(Breeder::find($fewPlans->id))->toBeNull();
});

test('breaks a full tie by keeping the oldest breeder (lowest id)', function () {
    $user = User::factory()->create();
    $older = breederWith($user, 'FR800');
    $newer = breederWith($user, 'FR800');

    $this->artisan('breeders:deduplicate', ['--force' => true])->assertOk();

    expect(Breeder::find($older->id))->not->toBeNull();
    expect(Breeder::find($newer->id))->toBeNull();
});

test('dry-run reports duplicates without modifying anything', function () {
    $user = User::factory()->create();
    breederWith($user, 'FR555', analyses: 2);
    $loser = breederWith($user, 'FR555', analyses: 1);

    $this->artisan('breeders:deduplicate')->assertOk();

    expect(Breeder::where('user_id', $user->id)->count())->toBe(2);
    expect(Analysis::where('breeder_id', $loser->id)->count())->toBe(1);
});
