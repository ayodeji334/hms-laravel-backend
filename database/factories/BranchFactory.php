<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->company() . ' Hospital', // Generating random hospital names
            'emergency_number' => $this->faker->unique()->numerify('080########'),
            'contact_address' => $this->faker->address,
            'email' => $this->faker->unique()->safeEmail,
            'created_by_id' => User::inRandomOrder()->first()->id ?? null,
            'last_updated_by_id' => User::inRandomOrder()->first()->id ?? null,
        ];
    }
}
