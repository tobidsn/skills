<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromoCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUserId = optional(User::where('email', 'super@antikode.com')->first())->id
            ?? optional(User::query()->first())->id;

        if (! $adminUserId) {
            return;
        }

        DB::table('promo_categories')->delete();

        DB::table('promo_categories')->insert([
            [
                'id' => Str::uuid(),
                'name' => 'Coca-cola',
                'description' => 'Coca-cola products',
                'is_active' => true,
                'sort_order' => 1,
                'created_by' => $adminUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
