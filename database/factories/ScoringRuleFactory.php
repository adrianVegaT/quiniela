<?php

namespace Database\Factories;

use App\Models\Quiniela;
use App\Models\ScoringRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoringRule>
 */
class ScoringRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiniela_id' => Quiniela::factory(),
            'points_exact_score' => 3,
            'points_winner_draw' => 1,
            'points_one_team_goals' => 1,
            'instructions' => fake()->optional()->paragraph(),
        ];
    }
}
