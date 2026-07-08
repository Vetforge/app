<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Aliment;
use App\Models\Melange;
use App\Models\MelangeAliment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MelangeAliment>
 */
class MelangeAlimentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'melange_id' => Melange::factory(),
            'aliment_id' => Aliment::factory(),
            'quantite' => fake()->randomFloat(2, 0.1, 50),
            'is_mb' => fake()->boolean(20),
        ];
    }
}
