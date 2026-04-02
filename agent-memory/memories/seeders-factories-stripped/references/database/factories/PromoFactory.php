<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Promo;
use App\Models\PromoCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Promo>
 */
final class PromoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Promo::class;

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
            'category_id' => PromoCategory::factory(),
            'points' => $this->faker->numberBetween(50, 500),
            'is_active' => $this->faker->boolean(80),
            'image' => $this->faker->imageUrl(640, 480, 'business'),
            'url' => $this->faker->url(),
            'image_detail' => $this->faker->imageUrl(800, 600, 'business'),
            'description' => $this->faker->paragraph(),
            'tnc' => $this->faker->paragraphs(3, true),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the promo is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the promo is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the promo has no end date (ongoing).
     */
    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'end_date' => null,
        ]);
    }

    /**
     * Indicate that the promo has no start date (always available).
     */
    public function alwaysAvailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => null,
            'end_date' => null,
        ]);
    }
}
