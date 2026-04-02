<?php

namespace Database\Seeders;

use App\Models\CheckinReward;
use App\Models\Reward;
use Illuminate\Database\Seeder;

class CheckinRewardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rewards = Reward::pluck('id')->toArray();

        $types = ['energy', 'score', 'reward', 'multiple'];

        $energyValues = [5, 10, 15, 20];

        $scoreValues = [10, 20, 30, 40, 50];

        $day1to7 = [
            [
                'day_number' => 1,
                'type' => 'energy',
                'energy' => 5,
                'score' => 0,
                'reward_id' => null,
            ],
            [
                'day_number' => 2,
                'type' => 'score',
                'energy' => 0,
                'score' => 10,
                'reward_id' => null,
            ],
            [
                'day_number' => 3,
                'type' => 'reward',
                'energy' => 0,
                'score' => 0,
                'reward_id' => ! empty($rewards) ? $rewards[array_rand($rewards)] : null,
            ],
            [
                'day_number' => 4,
                'type' => 'multiple',
                'energy' => 10,
                'score' => 20,
                'reward_id' => null,
            ],
            [
                'day_number' => 5,
                'type' => 'energy',
                'energy' => 5,
                'score' => 0,
                'reward_id' => null,
            ],
            [
                'day_number' => 6,
                'type' => 'energy',
                'energy' => 10,
                'score' => 0,
                'reward_id' => null,
            ],
            [
                'day_number' => 7,
                'type' => 'multiple',
                'energy' => 0,
                'score' => 30,
                'reward_id' => ! empty($rewards) ? $rewards[array_rand($rewards)] : null,
            ],
        ];

        foreach ($day1to7 as $data) {
            CheckinReward::create($data);
        }

        for ($day = 8; $day <= 30; $day++) {
            $type = $types[array_rand($types)];

            $data = [
                'day_number' => $day,
                'type' => $type,
                'energy' => 0,
                'score' => 0,
                'reward_id' => null,
            ];

            switch ($type) {
                case 'energy':
                    $data['energy'] = $energyValues[array_rand($energyValues)];
                    break;

                case 'score':
                    $data['score'] = $scoreValues[array_rand($scoreValues)];
                    break;

                case 'reward':
                    if (! empty($rewards)) {
                        $data['reward_id'] = $rewards[array_rand($rewards)];
                    }
                    break;

                case 'multiple':
                    // Random combination: energy+score, energy+reward, or score+reward
                    $combination = rand(1, 3);

                    if ($combination === 1) {
                        // Energy + Score
                        $data['energy'] = $energyValues[array_rand($energyValues)];
                        $data['score'] = $scoreValues[array_rand($scoreValues)];
                    } elseif ($combination === 2 && ! empty($rewards)) {
                        // Energy + Reward
                        $data['energy'] = $energyValues[array_rand($energyValues)];
                        $data['reward_id'] = $rewards[array_rand($rewards)];
                    } elseif ($combination === 3 && ! empty($rewards)) {
                        // Score + Reward
                        $data['score'] = $scoreValues[array_rand($scoreValues)];
                        $data['reward_id'] = $rewards[array_rand($rewards)];
                    } else {
                        // Fallback to energy + score if no rewards available
                        $data['energy'] = $energyValues[array_rand($energyValues)];
                        $data['score'] = $scoreValues[array_rand($scoreValues)];
                    }
                    break;
            }

            CheckinReward::create($data);
        }
    }
}
