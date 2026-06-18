<?php

namespace Database\Factories;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\PredictionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PredictionLog>
 */
class PredictionLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prediction_id' => Prediction::factory(),
            'match_id' => Fixture::factory(),
            'user_id' => User::factory(),
            'changed_by_user_id' => User::factory(),
            'old_home_score' => null,
            'old_away_score' => null,
            'new_home_score' => fake()->numberBetween(0, 5),
            'new_away_score' => fake()->numberBetween(0, 5),
            'action' => 'created',
            'reason' => null,
        ];
    }

    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'created',
            'old_home_score' => null,
            'old_away_score' => null,
        ]);
    }

    public function updated(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'updated',
            'old_home_score' => fake()->numberBetween(0, 5),
            'old_away_score' => fake()->numberBetween(0, 5),
        ]);
    }

    public function adminEdit(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'admin_edit',
            'old_home_score' => fake()->numberBetween(0, 5),
            'old_away_score' => fake()->numberBetween(0, 5),
            'reason' => fake()->sentence(),
        ]);
    }
}
