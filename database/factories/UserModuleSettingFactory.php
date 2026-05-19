<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserModuleSetting;
use App\Support\VeterinaryModules;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserModuleSetting>
 */
class UserModuleSettingFactory extends Factory
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
            'module' => $module,
            'settings' => VeterinaryModules::defaultSettings($module),
        ];
    }
}
