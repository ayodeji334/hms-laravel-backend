<?php

namespace Database\Seeders;

use App\Models\OrganisationAndHmo;
use Illuminate\Database\Seeder;

class OrganisationAndHmoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        OrganisationAndHmo::factory()->count(50)->create();
    }
}
