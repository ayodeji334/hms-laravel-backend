<?php

namespace Database\Seeders;

use App\Models\VitalSign;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VitalSignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        VitalSign::factory(700)->create();
    }
}
