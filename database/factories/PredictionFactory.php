<?php

namespace Database\Factories;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prediction>
 */
class PredictionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => Fixture::factory(),
            'user_id' => User::factory(),
            'home_score' => fake()->numberBetween(0, 5),
            'away_score' => fake()->numberBetween(0, 5),
            'is_partial' => false,
        ];
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'home_score' => null,
            'away_score' => null,
            'is_partial' => true,
        ]);
    }
}
