<?php

namespace Database\Factories;

use App\Models\Admin\AlertType;
use App\Models\Admin\EmergencyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertType>
 */
class AlertTypeFactory extends Factory
{
    protected $model = AlertType::class;

    public function definition(): array
    {
        return [
            'emergency_category_id' => EmergencyCategory::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'response_instructions' => null,
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'icon' => 'alert-circle',
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
