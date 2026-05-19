<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Aliment>
 */
class AlimentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code_inra' => fake()->optional()->bothify('####'),
            'type' => fake()->optional()->randomElement(['fourrage', 'concentre', 'mineral']),
            'libelle0' => fake()->words(2, true),
            'libelle1' => fake()->optional()->words(2, true),
            'libelle2' => null,
            'libelle3' => null,
            'libelle4' => null,
            'prix' => fake()->optional()->randomFloat(2, 0, 1000),
            'usage_aliment' => fake()->optional()->numberBetween(1, 5),
            'ms' => fake()->optional()->randomFloat(1, 0, 1000),
            'ufl' => fake()->optional()->randomFloat(3, 0, 2),
            'ufv' => fake()->optional()->randomFloat(3, 0, 2),
            'pdia' => fake()->optional()->randomFloat(1, 0, 200),
            'pdi' => fake()->optional()->randomFloat(1, 0, 300),
            'mat' => fake()->optional()->randomFloat(1, 0, 400),
            'cb' => fake()->optional()->randomFloat(1, 0, 500),
            'ndf' => fake()->optional()->randomFloat(1, 0, 700),
            'ca' => fake()->optional()->randomFloat(1, 0, 100),
            'p' => fake()->optional()->randomFloat(1, 0, 100),
        ];
    }

    public function systemique(): static
    {
        return $this->state(['user_id' => null]);
    }
}
