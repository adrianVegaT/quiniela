<?php

namespace Database\Factories;

use App\Models\Fixture;
use App\Models\Group;
use App\Models\Quiniela;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fixture>
 */
class FixtureFactory extends Factory
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
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'group_id' => null,
            'phase' => null,
            'round' => null,
            'match_date' => fake()->dateTimeBetween('-1 week', '+2 weeks'),
            'venue' => fake()->optional()->city(),
            'home_score' => null,
            'away_score' => null,
            'is_completed' => false,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'home_score' => null,
            'away_score' => null,
            'is_completed' => false,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'home_score' => fake()->numberBetween(0, 5),
            'away_score' => fake()->numberBetween(0, 5),
            'is_completed' => true,
        ]);
    }

    public function inGroup(Group $group): static
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => $group->id,
            'quiniela_id' => $group->quiniela_id,
        ]);
    }
}
