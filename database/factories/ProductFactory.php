<?php

namespace Database\Factories;

use App\Models\ProductManufacturer;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tracking_code' => Str::random(10),
            'brand_name' => $this->faker->word() . ' Tablets',
            'generic_name' => $this->faker->word(),
            'description' => $this->faker->paragraph,
            'is_enable_discount_price' => $this->faker->boolean,
            'sales_price' => $this->faker->randomFloat(2, 10, 1000),
            'unit_price' => $this->faker->randomFloat(2, 5, 500),
            'purchase_price' => $this->faker->randomFloat(2, 5, 500),
            'batch_code' => Str::random(8),
            'storage_condition' => $this->faker->randomElement(['Cool, dry place', 'Room temperature', 'Refrigerated']),
            'status' => $this->faker->randomElement(['DAMAGED', 'AVAILABLE', 'OUT-OF-STOCK', 'EXPIRED']),
            'quantity_purchase' => $this->faker->numberBetween(1, 100),
            'quantity_in_stock' => $this->faker->numberBetween(0, 200),
            'quantity_available_for_sales' => $this->faker->numberBetween(0, 200),
            'quantity_sold' => $this->faker->numberBetween(0, 100),
            'number_of_cartons' => $this->faker->numberBetween(1, 20),
            'number_of_packs' => $this->faker->numberBetween(1, 50),
            'minimum_number_before_reorder' => $this->faker->numberBetween(1, 10),
            'is_prescription_required' => $this->faker->boolean,
            'dosage_type' => $this->faker->randomElement(['TABLET', 'SYRUP', 'CAPSULE', 'INJECTION', 'CREAM', 'OINTMENT', 'OTHER']),
            'dosage_strength' => $this->faker->randomElement(['100mg', '200mg', '500mg', '5ml', '10ml']),
            'nafdac_code' => 'A' . Str::random(6),
            'weight' => $this->faker->randomFloat(2, 0.1, 5) . 'kg',
            'dimension' => $this->faker->randomElement(['10x5x3 cm', '15x10x7 cm', '20x12x5 cm']),
            'last_updated_on' => now(),
            'stock_updated_on' => now(),
            'stock_last_updated_by' => User::inRandomOrder()->first()?->id,
            'deleted_by_id' => null,
            'added_by_id' => User::inRandomOrder()->first()?->id,
            'type_id' => ProductType::inRandomOrder()->first()?->id,
            'last_updated_by_id' => User::inRandomOrder()->first()?->id,
            'manufacturer_id' => ProductManufacturer::inRandomOrder()->first()?->id,
            'expiry_date' => $this->faker->dateTimeBetween('+1 month', '+2 years'),
            'manufacturing_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
        ];
    }
}
