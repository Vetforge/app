<?php

use App\Models\Aliment;
use App\Models\User;

it('belongs to a user', function () {
    $user = User::factory()->create();
    $aliment = Aliment::factory()->create(['user_id' => $user->id]);

    expect($aliment->user)->toBeInstanceOf(User::class)
        ->and($aliment->user->id)->toBe($user->id);
});

it('can be systemique (without user)', function () {
    $aliment = Aliment::factory()->systemique()->create();

    expect($aliment->user_id)->toBeNull();
});
