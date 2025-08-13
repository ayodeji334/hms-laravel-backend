<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'firstname' => $this->faker->firstName,
            'middlename' => $this->faker->firstName,
            'lastname' => $this->faker->lastName,
            'name' => function (array $attributes) {
                return "{$attributes['firstname']} {$attributes['lastname']}";
            },
            'email' => $this->faker->unique()->safeEmail,
            'phone_number' => $this->faker->unique()->phoneNumber,
            'password' => Hash::make('password'),
            'role' => $this->faker->randomElement(['ADMIN', 'NURSE', 'PHARMACIST', 'DOCTOR', 'SUPER-ADMIN', 'LAB-TECHNOLOGIST', 'RECORD-KEEPER', 'CASHIER']),
            'gender' => $this->faker->randomElement(['MALE', 'FEMALE']),
            'marital_status' => $this->faker->randomElement(['SINGLE', 'MARRIED', 'DIVORCED', 'WIDOWED']),
            'religion' => $this->faker->randomElement(['CHRISTIANITY', 'ISLAM', 'OTHERS']),
            'nationality' => $this->faker->country,
            'is_active' => true,
            'staff_number' => strtoupper(Str::random(8)),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
