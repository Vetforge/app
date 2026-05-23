<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Aliment;
use App\Models\Ration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RationAliment>
 */
class RationAlimentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ration_id' => Ration::factory(),
            'aliment_id' => Aliment::factory(),
            'quantite' => fake()->randomFloat(2, 0.1, 30),
            'is_volonte' => fake()->boolean(10),
            'is_mb' => fake()->boolean(20),
        ];
    }
}
