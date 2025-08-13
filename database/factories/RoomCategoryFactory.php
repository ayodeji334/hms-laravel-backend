<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoomCategory>
 */
class RoomCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'General Ward',
                'Intensive Care Unit (ICU)',
                'Emergency Room',
                'Maternity Ward',
                'Surgical Ward',
                'Pediatric Ward',
                'Recovery Room',
                'Psychiatric Ward',
                'Isolation Room',
                'Private Suite',
                'Semi-Private Room',
                'Oncology Ward',
                'Cardiac Care Unit (CCU)',
                'Neonatal Intensive Care Unit (NICU)',
                'Dialysis Unit',
                'Burn Unit',
                'Orthopedic Ward',
                'Rehabilitation Room',
                'Radiology Room',
                'Pharmacy Storage Room'
            ]),
            'created_by_id' => User::inRandomOrder()->first()->id,
            'last_updated_by_id' => User::inRandomOrder()->first()->id,
        ];
    }
}
