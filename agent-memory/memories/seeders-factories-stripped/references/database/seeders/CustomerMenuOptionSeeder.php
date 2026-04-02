<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Food;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CustomerMenuOptionSeeder extends Seeder
{
    /**
     * Food category mappings
     */
    private const CATEGORY_MAPPINGS = [
        'main_dish' => [9, 10, 13], // Ayam, Ikan, Daging Sapi
        'side_dish' => [12, 7],     // Camilan, Makanan Penutup
        'drink' => [4],              // Minuman
    ];

    /**
     * Scoring rules per category
     */
    private const SCORING_RULES = [
        'main_dish' => [
            'recommended' => 40,
            'ok' => 25,
            'bad' => 10,
        ],
        'side_dish' => [
            'recommended' => 35,
            'ok' => 20,
            'bad' => 8,
        ],
        'drink' => [
            'recommended' => 25,
            'ok' => 15,
            'bad' => 6,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Query foods by category
        $foodsByCategory = $this->getFoodsByCategory();

        // Create customers
        $customers = $this->createCustomers();

        // Create menu options for each customer
        foreach ($customers as $customer) {
            $this->createMenuOptionsForCustomer($customer, $foodsByCategory);
        }

        $this->command->info('Customer menu options seeded successfully.');
    }

    /**
     * Query foods grouped by category
     *
     * @return array<string, array<int>>
     */
    private function getFoodsByCategory(): array
    {
        $foodsByCategory = [];

        foreach (self::CATEGORY_MAPPINGS as $category => $categoryIds) {
            $foodIds = Food::whereIn('food_category_id', $categoryIds)
                ->pluck('id')
                ->toArray();

            $foodsByCategory[$category] = $foodIds;

            $this->command->info(
                sprintf(
                    'Found %d foods for category "%s"',
                    count($foodIds),
                    $category
                )
            );
        }

        return $foodsByCategory;
    }

    /**
     * Create customers
     *
     * @return array<Customer>
     */
    private function createCustomers(): array
    {
        $customers = [];
        $codes = ['STU001', 'STF001', 'TCH001', 'STU002', 'STF002'];

        foreach ($codes as $code) {
            $customer = Customer::firstOrCreate(
                ['code' => $code],
                [
                    'id' => Str::uuid(),
                    'dialog' => null,
                    'best_reward_id' => random_int(1, 5),
                    'good_reward_id' => random_int(1, 5),
                ]
            );

            $customers[] = $customer;
        }

        $this->command->info(sprintf('Created %d customers', count($customers)));

        return $customers;
    }

    /**
     * Create menu options for a customer
     */
    private function createMenuOptionsForCustomer(Customer $customer, array $foodsByCategory): void
    {
        $menuOptions = [];

        foreach (self::CATEGORY_MAPPINGS as $category => $categoryIds) {
            $foodIds = $foodsByCategory[$category];
            $scores = self::SCORING_RULES[$category];

            // Ensure we have at least 3 food IDs (duplicate if necessary)
            $selectedFoodIds = $this->ensureMinimumFoods($foodIds, 3);

            // Create 3 options: recommended, ok, bad
            $menuOptions[] = [
                'id' => Str::uuid(),
                'customer_id' => $customer->id,
                'category' => $category,
                'food_id' => $selectedFoodIds[0],
                'score' => $scores['recommended'],
                'order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $menuOptions[] = [
                'id' => Str::uuid(),
                'customer_id' => $customer->id,
                'category' => $category,
                'food_id' => $selectedFoodIds[1],
                'score' => $scores['ok'],
                'order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $menuOptions[] = [
                'id' => Str::uuid(),
                'customer_id' => $customer->id,
                'category' => $category,
                'food_id' => $selectedFoodIds[2],
                'score' => $scores['bad'],
                'order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert all menu options for this customer
        DB::table('customer_menu_options')->insert($menuOptions);

        $this->command->info(
            sprintf(
                'Created 9 menu options for customer %s',
                $customer->code
            )
        );
    }

    /**
     * Ensure minimum number of foods, duplicating if necessary
     *
     * @param  array<int>  $foodIds
     * @return array<int|null>
     */
    private function ensureMinimumFoods(array $foodIds, int $minimum): array
    {
        if (empty($foodIds)) {
            // Return nulls if no foods available
            return array_fill(0, $minimum, null);
        }

        $result = [];
        $count = count($foodIds);

        for ($i = 0; $i < $minimum; $i++) {
            // Cycle through available foods if we need more than available
            $result[] = $foodIds[$i % $count];
        }

        return $result;
    }
}
