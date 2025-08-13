<?php

namespace Database\Factories;

use App\Enums\TitleTypes;
use App\RelationshipTypes;
use App\MaritalStatus;
use App\Models\Patient;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(
            ['STAFF', 'STUDENT', 'OTHERS']
        );

        return [
            'patient_reg_no' => 'PAT' . $this->faker->unique()->numberBetween(100000, 999999),
            'email' => $this->faker->unique()->safeEmail,
            'firstname' => $this->faker->firstName,
            'middlename' => $this->faker->lastName,
            'lastname' => $this->faker->lastName,
            'hall_of_residence' => $this->faker->randomElement(['Hall A', 'Hall B', 'Hall C']),
            'contact_address' => $this->faker->address,
            'blood_group' => $this->faker->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
            'genotype' => $this->faker->randomElement(['AA', 'AS', 'SS', 'SC']),
            'room_number' => $this->faker->numberBetween(1, 500),
            'permanent_address' => $this->faker->address,
            'phone_number' => $this->faker->phoneNumber,
            'state_of_origin' => $this->faker->state,
            'lga' => $this->faker->city,
            'religion' => $this->faker->randomElement(['Christianity', 'Islam', 'Traditional', 'Others']),
            'next_of_kin_firstname' => $this->faker->firstName,
            'next_of_kin_lastname' => $this->faker->lastName,
            'next_of_kin_contact_address' => $this->faker->address,
            'next_of_kin_phone_number' => $this->faker->phoneNumber,
            'next_of_kin_relationship' => $this->faker->randomElement(RelationshipTypes::cases())->value,
            'is_active' => $this->faker->boolean(90),
            'type' => $type,
            'age' => $this->faker->numberBetween(1, 70),
            'matriculation_number' => $type === "STUDENT" ? strtoupper(Str::random(10)) : null,
            'staff_number' => $type === "STAFF" ? strtoupper(Str::random(8)) : null,
            'gender' => $this->faker->randomElement(['Male', 'Female']),
            'occupation' => $this->faker->optional()->jobTitle,
            'tribe' => $this->faker->optional()->randomElement(['Yoruba', 'Igbo', 'Hausa', 'Edo', 'Others']),
            'marital_status' => $this->faker->randomElement(MaritalStatus::cases())->value,
            'nationality' => 'Nigerian',
            'level' => $type === "STUDENT" ? $this->faker->optional()->randomElement(['100', '200', '300', '400', '500']) : null,
            'last_updated_on' => now(),
            'password' => bcrypt('password'),
            'title' => $this->faker->randomElement(TitleTypes::cases())->value,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Patient $patient) {
            Wallet::factory()->create(['patient_id' => $patient->id]);
        });
    }
}
