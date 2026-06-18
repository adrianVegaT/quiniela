<?php

namespace Database\Factories;

use App\Models\Quiniela;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Quiniela>
 */
class QuinielaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'code' => strtoupper(Str::random(8)),
            'owner_id' => User::factory(),
            'status' => 'active',
            'prediction_edit_deadline' => fake()->optional()->dateTimeBetween('+1 week', '+1 month'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'prediction_edit_deadline' => now()->subDay(),
        ]);
    }

    public function historical(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'historical',
            'prediction_edit_deadline' => now()->subMonth(),
        ]);
    }
}
