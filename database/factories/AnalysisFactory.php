<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\User;
use App\Support\VeterinaryModules;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Analysis>
 */
class AnalysisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $module = fake()->randomElement(VeterinaryModules::slugs());

        return [
            'user_id' => User::factory(),
            'breeder_id' => fn (array $attributes): int => Breeder::factory()->create(['user_id' => $attributes['user_id']])->id,
            'animal_nom' => fake()->optional()->firstName(),
            'module' => $module,
            'status' => 'complete',
            'sampled_at' => fake()->date(),
            'analyzed_at' => fake()->date(),
            'intervenant' => fake()->name(),
            'payload' => [],
            'results' => [],
            'settings_snapshot' => VeterinaryModules::defaultSettings($module),
        ];
    }
}
