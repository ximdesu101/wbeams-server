<?php

namespace Database\Factories;

use App\Models\Operator\Operator;
use App\Enums\OperatorStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Operator>
 */
class OperatorFactory extends Factory
{
    protected $model = Operator::class;

    public function definition(): array
    {
        return [
            'operator_id' => strtoupper(fake()->unique()->bothify('OP-####')),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'contact_number' => fake()->unique()->numerify('09#########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'status' => OperatorStatus::Active,
            'activated_at' => now(),
            'activation_token' => null,
            'activation_token_expires_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => OperatorStatus::Active,
            'activated_at' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => OperatorStatus::Inactive,
            'activated_at' => null,
            'activation_token' => Str::random(64),
            'activation_token_expires_at' => now()->addHours(48),
        ]);
    }
}
