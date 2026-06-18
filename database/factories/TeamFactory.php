<?php

namespace Database\Factories;

use App\Models\Quiniela;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected static array $teamNames = [
        'Argentina', 'Brasil', 'Francia', 'Alemania', 'España',
        'Inglaterra', 'Italia', 'Portugal', 'Países Bajos', 'Bélgica',
        'Croacia', 'Uruguay', 'México', 'Estados Unidos', 'Senegal',
        'Japón', 'Corea del Sur', 'Australia', 'Ghana', 'Camerún',
        'Marruecos', 'Arabia Saudita', 'Túnez', 'Costa Rica',
        'Colombia', 'Chile', 'Ecuador', 'Paraguay', 'Perú', 'Bolivia',
        'Venezuela', 'Panamá',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiniela_id' => Quiniela::factory(),
            'name' => fake()->unique()->randomElement(self::$teamNames),
            'flag_url' => fake()->optional()->url(),
        ];
    }
}
