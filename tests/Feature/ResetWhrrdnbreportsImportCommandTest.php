<?php

use App\Models\Aliment;
use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\User;

it('resets whrrdnbreports imported data for the target user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'legacy_export_').'.php';

    file_put_contents($path, "<?php\n");

    $breeder = Breeder::query()->create([
        'user_id' => $user->id,
        'name' => 'GAEC Import',
    ]);
    $otherBreeder = Breeder::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'GAEC Autre',
    ]);

    Aliment::query()->create([
        'user_id' => $user->id,
        'libelle0' => 'Foin import',
    ]);
    Aliment::query()->create([
        'user_id' => $otherUser->id,
        'libelle0' => 'Foin autre',
    ]);
    Aliment::query()->create([
        'user_id' => null,
        'libelle0' => 'Foin systeme',
    ]);

    Analysis::query()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'gaz-du-sang',
        'status' => 'complete',
        'payload' => [],
    ]);
    Analysis::query()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'bse-allaitant',
        'status' => 'complete',
        'payload' => [],
    ]);
    Analysis::query()->create([
        'user_id' => $otherUser->id,
        'breeder_id' => $otherBreeder->id,
        'module' => 'gaz-du-sang',
        'status' => 'complete',
        'payload' => [],
    ]);

    try {
        $this->artisan('legacy:reset-whrrdnbreports', [
            'path' => $path,
            '--user' => (string) $user->id,
            '--cabinet' => 'rieupeyroux',
        ])->assertSuccessful();
    } finally {
        @unlink($path);
    }

    expect(Breeder::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(Aliment::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(Analysis::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(Breeder::query()->where('user_id', $otherUser->id)->count())->toBe(1)
        ->and(Aliment::query()->where('user_id', $otherUser->id)->count())->toBe(1)
        ->and(Analysis::query()->where('user_id', $otherUser->id)->count())->toBe(1)
        ->and(Aliment::query()->whereNull('user_id')->count())->toBe(1);
});

it('dry-runs the whrrdnbreports reset without deleting data', function () {
    $user = User::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'legacy_export_').'.php';

    file_put_contents($path, "<?php\n");

    $breeder = Breeder::query()->create([
        'user_id' => $user->id,
        'name' => 'GAEC Import',
    ]);

    Aliment::query()->create([
        'user_id' => $user->id,
        'libelle0' => 'Foin import',
    ]);

    Analysis::query()->create([
        'user_id' => $user->id,
        'breeder_id' => $breeder->id,
        'module' => 'gaz-du-sang',
        'status' => 'complete',
        'payload' => [],
    ]);

    try {
        $this->artisan('legacy:reset-whrrdnbreports', [
            'path' => $path,
            '--user' => (string) $user->id,
            '--dry-run' => true,
        ])->assertSuccessful();
    } finally {
        @unlink($path);
    }

    expect(Breeder::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(Aliment::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(Analysis::query()->where('user_id', $user->id)->count())->toBe(1);
});
