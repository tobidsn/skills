<?php

namespace Database\Seeders;

use App\Models\Promo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VoucherSeeder extends Seeder
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

        $promos = Promo::all();

        DB::table('vouchers')->delete();

        // Generate sample voucher codes
        $voucherCodes = [
            'SPRING2024', 'BLACKFRIDAY', 'WELCOME50', 'HOLIDAY500',
            'SUMMER100', 'WINTER200', 'NEWYEAR300', 'EASTER150',
            'CHRISTMAS400', 'BIRTHDAY75', 'LOYALTY250', 'REFERRAL125',
        ];

        $vouchers = [];

        foreach ($voucherCodes as $index => $code) {
            $promo = $promos->random();

            $vouchers[] = [
                'promo_id' => $promo->id,
                'code' => $code.str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'image' => null,
                'member_id' => null,
                'email' => null,
                'is_used' => false,
                'used_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('vouchers')->insert($vouchers);

        $this->command->info('Vouchers seeded successfully!');
        $this->command->info('Created '.count($vouchers).' vouchers');
    }
}
