<?php

namespace Database\Factories;

use App\Models\Recipient\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Recipient>
 */
class RecipientFactory extends Factory
{
    protected $model = Recipient::class;

    public function definition(): array
    {
        return [
            'id_number' => fake()->unique()->numerify('########'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'role' => fake()->randomElement(['student', 'faculty', 'staff']),
            'student_program' => null,
            'student_year' => null,
            'contact_number' => fake()->unique()->numerify('09#########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ];
    }

    public function student(): static
    {
        return $this->state(fn () => [
            'role' => 'student',
            'student_program' => fake()->randomElement(['BSIT', 'BSCrim', 'BEED', 'BTLED']),
            'student_year' => fake()->randomElement(['1st year', '2nd year', '3rd year', '4th year']),
        ]);
    }

    public function faculty(): static
    {
        return $this->state(fn () => ['role' => 'faculty']);
    }

    public function staff(): static
    {
        return $this->state(fn () => ['role' => 'staff']);
    }
}
