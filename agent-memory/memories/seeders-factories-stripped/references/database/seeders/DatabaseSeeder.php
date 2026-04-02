<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);
        $this->call(UsersSeeder::class);
        $this->call(AssignSuperAdminRole::class);
        $this->call(SettingsTableSeeder::class);
        $this->call(MediaTableSeeder::class);
        $this->call(RewardSeeder::class);
        $this->call(CheckinRewardSeeder::class);
        $this->call(PromoCategorySeeder::class);
        $this->call(PromoSeeder::class);
        $this->call(VoucherSeeder::class);
        $this->call(SliderSeeder::class);
        $this->call(EnergySeeder::class);
        $this->call(PageSeeder::class);
    }
}
