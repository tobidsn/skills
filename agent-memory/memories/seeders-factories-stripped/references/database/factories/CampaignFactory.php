<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
final class CampaignFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Campaign::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+1 month');
        $endDate = $this->faker->dateTimeBetween($startDate, '+3 months');

        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraphs(3, true),
            'required_quantity' => $this->faker->numberBetween(1, 10),
            'image' => $this->faker->imageUrl(640, 480, 'business'),
            'product_name' => $this->faker->sentence(3),
            'voucher_quota' => $this->faker->numberBetween(100, 1000),
            'voucher_claimed' => $this->faker->numberBetween(0, 100),
            'is_active' => $this->faker->boolean(80),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the campaign is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the campaign is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
