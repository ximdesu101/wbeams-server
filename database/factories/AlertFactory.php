<?php

namespace Database\Factories;

use App\Models\Admin\AlertType;
use App\Models\Operator\Alert;
use App\Models\Operator\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    public function definition(): array
    {
        return [
            'alert_type_id' => AlertType::factory(),
            'operator_id' => Operator::factory(),
            'title' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'response_instructions' => null,
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'target_roles' => ['student', 'faculty', 'staff'],
            'channels' => ['email'],
            'status' => 'sent',
            'sent_at' => now(),
        ];
    }

    /** Alert targeting specific roles only. */
    /**
     * @param  array<int, string>  $roles
     */
    public function forRoles(array $roles): static
    {
        return $this->state(fn () => ['target_roles' => $roles]);
    }

    /** Alert with email channel. */
    public function withEmail(): static
    {
        return $this->state(fn () => ['channels' => ['email']]);
    }

    /** Alert without email channel (in-app only). */
    public function withoutEmail(): static
    {
        return $this->state(fn () => ['channels' => ['web_push']]);
    }
}
