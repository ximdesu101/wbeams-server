<?php

namespace Database\Factories;

use App\Models\Admin\EmergencyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmergencyCategory>
 */
class EmergencyCategoryFactory extends Factory
{
    protected $model = EmergencyCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
