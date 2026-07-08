<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CategorieAnimal;
use App\Models\PlanRationnement;
use App\Models\Ration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ration>
 */
class RationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plan_rationnement_id' => PlanRationnement::factory(),
            'nom' => fake()->words(2, true),
            'effectif' => fake()->optional()->numberBetween(1, 200),
            'lait_potentiel305j' => fake()->optional()->numberBetween(3000, 12000),
            'poids_vif' => fake()->optional()->numberBetween(400, 800),
            'pourcentage_primipare' => fake()->optional()->numberBetween(0, 100),
            'nec' => fake()->optional()->randomFloat(1, 1, 5),
            'tb_annuel' => fake()->optional()->randomFloat(1, 30, 50),
            'tp_annuel' => fake()->optional()->randomFloat(1, 28, 40),
            'activite' => fake()->optional()->randomElement(['stabulation', 'entravee', 'plaine', 'vallon', 'montagne']),
            'temperature_ambiante' => fake()->optional()->randomFloat(1, -10, 35),
            'race' => fake()->optional()->randomElement(['limousine', 'croiselaitiere', 'montbeliarde']),
            'categorie_animal' => fake()->optional()->randomElement([
                CategorieAnimal::VacheLaitiere->value,
                CategorieAnimal::VacheAllaitante->value,
            ]),
            'mois_lactation' => fake()->optional()->randomFloat(2, 0, 10),
            'mois_gestation' => fake()->optional()->randomFloat(2, 0, 9),
        ];
    }

    public function categorie(CategorieAnimal $categorie): static
    {
        return $this->state(fn () => ['categorie_animal' => $categorie->value]);
    }

    public function vacheLaitiere(): static
    {
        return $this->categorie(CategorieAnimal::VacheLaitiere);
    }

    public function vacheAllaitante(): static
    {
        return $this->categorie(CategorieAnimal::VacheAllaitante);
    }

    public function bovinCroissance(): static
    {
        return $this->categorie(CategorieAnimal::BovinCroissance)->state(fn () => [
            'poids_vif' => fake()->numberBetween(200, 500),
            'gmq' => fake()->numberBetween(600, 1400),
            'stade_physiologique' => 'croissance',
        ]);
    }

    public function brebisLaitiere(): static
    {
        return $this->categorie(CategorieAnimal::BrebisLaitiere)->state(fn () => [
            'poids_vif' => fake()->numberBetween(50, 90),
            'race' => fake()->randomElement(['lacaune', 'manech']),
            'mpc' => fake()->randomFloat(1, 45, 60),
        ]);
    }

    public function chevreLaitiere(): static
    {
        return $this->categorie(CategorieAnimal::ChevreLaitiere)->state(fn () => [
            'poids_vif' => fake()->numberBetween(50, 80),
            'mfc' => fake()->randomFloat(1, 30, 45),
            'mpc' => fake()->randomFloat(1, 28, 38),
        ]);
    }
}
