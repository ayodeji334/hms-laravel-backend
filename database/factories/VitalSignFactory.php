<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VitalSign>
 */
class VitalSignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_at' => $this->faker->dateTimeBetween('-2 months', 'now'),
            'patient_id' => Patient::inRandomOrder()->first()->id ?? null,
            'added_by_id' => User::inRandomOrder()->first()->id ?? null,
            'last_updated_by_id' => User::inRandomOrder()->first()->id ?? null,
            'respiratory_rate' => $this->faker->randomNumber(3),
            'blood_pressure' => $this->faker->randomNumber(3) . "/" . $this->faker->randomNumber(3),
            'heart_rate' => $this->faker->randomNumber(3),
            'temperature' => $this->faker->randomNumber(3),
        ];
    }
}
