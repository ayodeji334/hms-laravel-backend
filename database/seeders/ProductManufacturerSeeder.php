<?php

namespace Database\Seeders;

use App\Models\ProductManufacturer;
use Illuminate\Database\Seeder;

class ProductManufacturerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProductManufacturer::factory()->count(20)->create();
    }
}
