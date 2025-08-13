<?php

namespace Database\Seeders;

use App\Models\OrganisationAndHmoPayment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganisationAndHmoPaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        OrganisationAndHmoPayment::factory()->count(400)->create();
    }
}
