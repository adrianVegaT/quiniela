<?php

namespace Database\Seeders;

use App\Models\Fixture;
use App\Models\Group;
use App\Models\Prediction;
use App\Models\Quiniela;
use App\Models\ScoringRule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuinielaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::factory(5)->create();

        $quiniela = Quiniela::factory()->create([
            'name' => 'Mundial FIFA 2026',
            'description' => 'Quiniela de prueba para el Mundial',
            'owner_id' => $users[0]->id,
            'status' => 'active',
            'prediction_edit_deadline' => now()->addWeeks(2),
        ]);

        ScoringRule::factory()->create([
            'quiniela_id' => $quiniela->id,
            'points_exact_score' => 3,
            'points_winner_draw' => 1,
            'points_one_team_goals' => 1,
            'instructions' => 'Acierto exacto: 3 pts. Acertar ganador/empate: 1 pt. Acertar goles de un equipo: 1 pt.',
        ]);

        $groupNames = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $groups = [];
        $teamsByGroup = [];

        $allTeamNames = [
            'Argentina', 'Brasil', 'Francia', 'Inglaterra',
            'España', 'Alemania', 'Portugal', 'Países Bajos',
            'Italia', 'Croacia', 'Uruguay', 'México',
            'Estados Unidos', 'Senegal', 'Japón', 'Corea del Sur',
            'Marruecos', 'Bélgica', 'Colombia', 'Chile',
            'Ecuador', 'Australia', 'Ghana', 'Camerún',
            'Arabia Saudita', 'Túnez', 'Costa Rica', 'Paraguay',
            'Perú', 'Bolivia', 'Venezuela', 'Panamá',
        ];

        $teamIndex = 0;

        foreach ($groupNames as $letter) {
            $group = Group::factory()->create([
                'quiniela_id' => $quiniela->id,
                'name' => 'Grupo '.$letter,
            ]);
            $groups[] = $group;
            $teamsByGroup[$letter] = [];

            for ($i = 0; $i < 4; $i++) {
                $team = Team::factory()->create([
                    'quiniela_id' => $quiniela->id,
                    'name' => $allTeamNames[$teamIndex],
                ]);
                $team->groups()->attach($group->id);
                $teamsByGroup[$letter][] = $team;
                $teamIndex++;
            }
        }

        $oneMonthAgo = now()->subMonth();
        $oneMonthAhead = now()->addMonth();

        $matchCount = 0;

        foreach ($groups as $group) {
            $groupLetter = substr($group->name, -1);
            $teams = $teamsByGroup[$groupLetter];

            $pairings = [
                [0, 1], [2, 3],
                [0, 2], [1, 3],
                [0, 3], [1, 2],
            ];

            foreach ($pairings as $index => $pair) {
                $matchCount++;
                $matchDate = $index < 2
                    ? (clone $oneMonthAgo)->addDays($matchCount * 3)
                    : (clone $oneMonthAgo)->addDays($matchCount * 3 + 14);

                $isCompleted = $index < 2;

                Fixture::factory()->create([
                    'quiniela_id' => $quiniela->id,
                    'home_team_id' => $teams[$pair[0]]->id,
                    'away_team_id' => $teams[$pair[1]]->id,
                    'group_id' => $group->id,
                    'match_date' => $matchDate,
                    'home_score' => $isCompleted ? fake()->numberBetween(0, 4) : null,
                    'away_score' => $isCompleted ? fake()->numberBetween(0, 4) : null,
                    'is_completed' => $isCompleted,
                ]);
            }
        }

        foreach ($users as $user) {
            $quiniela->participants()->attach($user->id, [
                'joined_at' => now()->subDays(fake()->numberBetween(1, 10)),
            ]);
        }

        $matches = $quiniela->matches()->where('is_completed', true)->get();
        foreach ($users as $user) {
            foreach ($matches as $match) {
                Prediction::factory()->create([
                    'match_id' => $match->id,
                    'user_id' => $user->id,
                    'home_score' => fake()->numberBetween(0, 4),
                    'away_score' => fake()->numberBetween(0, 4),
                    'is_partial' => false,
                ]);
            }
        }

        $pendingMatches = $quiniela->matches()->where('is_completed', false)->take(12)->get();
        foreach ($users as $user) {
            foreach ($pendingMatches as $match) {
                Prediction::factory()->create([
                    'match_id' => $match->id,
                    'user_id' => $user->id,
                    'home_score' => fake()->numberBetween(0, 4),
                    'away_score' => fake()->numberBetween(0, 4),
                    'is_partial' => false,
                ]);
            }
        }
    }
}
