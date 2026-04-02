<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EnergySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('energies')->delete();

        DB::table('energies')->insert([
            [
                'energy' => 5,
                'points' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'energy' => 10,
                'points' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'energy' => 15,
                'points' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
