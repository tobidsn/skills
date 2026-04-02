<?php

namespace Database\Seeders;

use App\Models\PromoCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUserId = optional(User::where('email', 'super@antikode.com')->first())->id
            ?? optional(User::query()->first())->id;

        if (! $adminUserId) {
            return; // No users available to associate created_by
        }

        $promoCategories = PromoCategory::all();
        DB::table('promos')->delete();

        DB::table('promos')->insert([
            [
                'id' => Str::uuid(),
                'title' => 'Jaket Jeans Coca-Cola',
                'category_id' => $promoCategories->random()->id,
                'points' => 0,
                'is_active' => true,
                'description' => 'Celebrate spring with amazing discounts and rewards.',
                'tnc' => '<p>Terms and conditions apply. Valid until end of promotion period.</p>',
                'start_date' => now()->format('Y-m-d'),
                'end_date' => now()->addDays(30)->format('Y-m-d'),
                'created_by' => $adminUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'title' => 'Jaket Uniqlo Coca-Cola',
                'category_id' => $promoCategories->first()->id,
                'points' => 0,
                'is_active' => false,
                'description' => 'Huge discounts on Black Friday with bonus points.',
                'tnc' => '<p>Limited time offer. Valid only on Black Friday.</p>',
                'start_date' => now()->addDays(60)->format('Y-m-d'),
                'end_date' => now()->addDays(61)->format('Y-m-d'),
                'created_by' => $adminUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'title' => 'T-Shirt Uniqlo',
                'category_id' => $promoCategories->first()->id,
                'points' => 0,
                'is_active' => true,
                'description' => 'Welcome bonus for new members joining our platform.',
                'tnc' => '<p>Available for first-time members only.</p>',
                'start_date' => now()->format('Y-m-d'),
                'end_date' => null,
                'created_by' => $adminUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'title' => 'Labubu Coca-Cola',
                'category_id' => $promoCategories->first()->id,
                'points' => 0,
                'is_active' => true,
                'description' => 'Special holiday campaign with maximum points reward.',
                'tnc' => '<p>Holiday season special. Points valid for 90 days.</p>',
                'start_date' => now()->addDays(30)->format('Y-m-d'),
                'end_date' => now()->addDays(120)->format('Y-m-d'),
                'created_by' => $adminUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'title' => 'Topi Coca-Cola',
                'category_id' => $promoCategories->random()->id,
                'points' => 0,
                'is_active' => true,
                'description' => 'Special holiday campaign with maximum points reward.',
                'tnc' => '<p>Holiday season special. Points valid for 90 days.</p>',
                'start_date' => now()->format('Y-m-d'),
                'end_date' => now()->addDays(60)->format('Y-m-d'),
                'created_by' => $adminUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'title' => 'Raglan Coca-Cola',
                'category_id' => $promoCategories->first()->id,
                'points' => 0,
                'is_active' => true,
                'description' => 'Special holiday campaign with maximum points reward.',
                'tnc' => '<p>Holiday season special. Points valid for 90 days.</p>',
                'start_date' => now()->format('Y-m-d'),
                'end_date' => now()->addDays(90)->format('Y-m-d'),
                'created_by' => $adminUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
