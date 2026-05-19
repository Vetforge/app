<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlanRationnement>
 */
class PlanRationnementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nom' => fake()->words(3, true),
            'date' => fake()->date(),
            'inra' => fake()->optional()->randomElement(['2007', '2018']),
        ];
    }
}
