<?php

namespace Database\Factories;

use App\Models\Melange;
use App\Models\Ration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RationMelange>
 */
class RationMelangeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ration_id' => Ration::factory(),
            'melange_id' => Melange::factory(),
            'quantite' => fake()->randomFloat(2, 0.1, 30),
            'is_volonte' => fake()->boolean(10),
            'is_mb' => fake()->boolean(20),
        ];
    }
}
