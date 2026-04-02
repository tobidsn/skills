<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Seeder;

final class CampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();

        $data = [];

        // Create stamps 1-10, with rewards only at 5 and 10
        for ($i = 1; $i <= 10; $i++) {
            $hasReward = in_array($i, [5, 10]);

            $data[] = [
                'title' => "Stamp {$i}",
                'description' => "Collect {$i} stamp".($i > 1 ? 's' : '').' to'.($hasReward ? ' unlock reward' : ' progress in your stamp collection'),
                'start_date' => now(),
                'end_date' => now()->addDays(365),
                'is_active' => true,
                'required_quantity' => $i,
                'product_name' => 'McDonald\'s Product',
                'is_reward' => $hasReward,
                'reward_id' => $hasReward ? ($i == 5 ? 1 : 2) : null,
                'created_by' => $user->id,
                'created_at' => now(),
            ];
        }

        foreach ($data as $campaignData) {
            Campaign::updateOrCreate(
                ['title' => $campaignData['title']],
                $campaignData
            );
        }

        // Log the seeding action
        $this->command->info('Stamp campaigns seeded successfully with '.count($data).' campaigns (1-10).');
        $this->command->info('Rewards are available at stamps 5 and 10.');

        // Log the created campaigns with reward information
        foreach ($data as $campaign) {
            $rewardText = $campaign['is_reward'] ? ' (WITH REWARD)' : '';
            $this->command->info('Created campaign: '.$campaign['title'].$rewardText);
        }

        // Final message
        $this->command->info('All stamp campaigns have been seeded successfully.');
    }
}
