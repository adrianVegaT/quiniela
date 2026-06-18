<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Quiniela;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected static array $groupLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiniela_id' => Quiniela::factory(),
            'name' => 'Grupo '.fake()->randomElement(self::$groupLetters),
        ];
    }
}
