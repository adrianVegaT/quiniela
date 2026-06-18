<?php

namespace App\Services;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\Quiniela;
use App\Models\ScoringRule;

class ScoringService
{
    public function calculateMatchScore(Prediction $prediction, Fixture $fixture, ScoringRule $rules): array
    {
        $exactScore = 0;
        $winnerDraw = 0;
        $oneTeamGoals = 0;

        if ($prediction->home_score === null || $prediction->away_score === null) {
            return compact('exactScore', 'winnerDraw', 'oneTeamGoals');
        }

        if ($fixture->home_score === null || $fixture->away_score === null) {
            return compact('exactScore', 'winnerDraw', 'oneTeamGoals');
        }

        $exactScore = (
            $prediction->home_score === $fixture->home_score
            && $prediction->away_score === $fixture->away_score
        ) ? $rules->points_exact_score : 0;

        if ($exactScore === 0) {
            $predSign = $this->sign($prediction->home_score, $prediction->away_score);
            $matchSign = $this->sign($fixture->home_score, $fixture->away_score);

            if ($predSign === $matchSign) {
                $winnerDraw = $rules->points_winner_draw;
            }

            if ($prediction->home_score === $fixture->home_score
                || $prediction->away_score === $fixture->away_score) {
                $oneTeamGoals = $rules->points_one_team_goals;
            }
        }

        return compact('exactScore', 'winnerDraw', 'oneTeamGoals');
    }

    public function scoreMatch(Fixture $fixture): void
    {
        $quiniela = $fixture->quiniela;
        $rules = $quiniela->scoringRule;

        if (! $rules) {
            return;
        }

        $predictions = $fixture->predictions()->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->get();

        foreach ($predictions as $prediction) {
            $scores = $this->calculateMatchScore($prediction, $fixture, $rules);

            $prediction->update([
                'exact_score_points' => $scores['exactScore'],
                'winner_draw_points' => $scores['winnerDraw'],
                'one_team_goals_points' => $scores['oneTeamGoals'],
            ]);
        }
    }

    public function recalculateQuiniela(Quiniela $quiniela): void
    {
        $completedMatches = $quiniela->matches()->where('is_completed', true)->get();

        foreach ($completedMatches as $fixture) {
            $this->scoreMatch($fixture);
        }
    }

    public function leaderboard(Quiniela $quiniela): array
    {
        $participants = $quiniela->participants()
            ->whereNull('quiniela_user.deleted_at')
            ->get();

        $totalMatches = $quiniela->matches()->count();
        $completedMatches = $quiniela->matches()->where('is_completed', true)->count();
        $rules = $quiniela->scoringRule;

        $rows = $participants->map(function ($user) use ($quiniela) {
            $predictions = Prediction::where('user_id', $user->id)
                ->whereIn('match_id', $quiniela->matches()->where('is_completed', true)->select('id'))
                ->get();

            $exactCount = $predictions->where('exact_score_points', '>', 0)->count();
            $winnerCount = $predictions->where('winner_draw_points', '>', 0)->count();
            $goalsCount = $predictions->where('one_team_goals_points', '>', 0)->count();
            $totalPoints = $predictions->sum('exact_score_points')
                + $predictions->sum('winner_draw_points')
                + $predictions->sum('one_team_goals_points');

            return [
                'user' => $user,
                'predictions_count' => $predictions->count(),
                'exact_score_count' => $exactCount,
                'winner_draw_count' => $winnerCount,
                'one_team_goals_count' => $goalsCount,
                'total_points' => $totalPoints,
            ];
        });

        $sorted = $rows->sortBy([
            ['total_points', 'desc'],
            ['exact_score_count', 'desc'],
            fn ($a, $b) => $a['user']->name <=> $b['user']->name,
        ])->values()->toArray();

        return [
            'rows' => $sorted,
            'total_matches' => $totalMatches,
            'completed_matches' => $completedMatches,
            'rules' => $rules,
        ];
    }

    private function sign(int $home, int $away): string
    {
        if ($home > $away) {
            return 'home';
        }
        if ($home < $away) {
            return 'away';
        }

        return 'draw';
    }
}
