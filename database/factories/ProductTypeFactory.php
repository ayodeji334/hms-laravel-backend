<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductType>
 */
class ProductTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'last_updated_on' => now(),
            'added_by_id' => User::inRandomOrder()->first()?->id,
            'last_updated_by_id' => User::inRandomOrder()->first()?->id,
            'deleted_by_id' => null,
        ];
    }
}
