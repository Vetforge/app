<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Melange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Melange>
 */
class MelangeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nom' => fake()->words(2, true),
        ];
    }
}
