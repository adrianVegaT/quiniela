<?php

namespace App\Services;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\PredictionLog;
use App\Models\Quiniela;
use App\Models\User;

class PredictionService
{
    public function savePrediction(Fixture $fixture, User $user, ?int $homeScore, ?int $awayScore): Prediction
    {
        $quiniela = $fixture->quiniela;

        $isOwner = $user->id === $quiniela->owner_id;
        $isParticipant = $quiniela->participants()
            ->where('user_id', $user->id)
            ->whereNull('quiniela_user.deleted_at')
            ->exists();

        if (! $isOwner && ! $isParticipant) {
            throw new \RuntimeException('No eres participante de esta quiniela.');
        }

        if (! $this->canEdit($quiniela, $user)) {
            throw new \RuntimeException('No puedes editar predicciones después de la fecha límite.');
        }

        $this->validateScoreRange($homeScore);
        $this->validateScoreRange($awayScore);

        $existing = Prediction::where('match_id', $fixture->id)
            ->where('user_id', $user->id)
            ->first();

        $wasNew = $existing === null;
        $oldHome = $existing?->home_score;
        $oldAway = $existing?->away_score;

        $prediction = Prediction::updateOrCreate(
            ['match_id' => $fixture->id, 'user_id' => $user->id],
            [
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'is_partial' => $homeScore === null || $awayScore === null,
            ]
        );

        if ($wasNew || $oldHome !== $homeScore || $oldAway !== $awayScore) {
            PredictionLog::create([
                'prediction_id' => $prediction->id,
                'match_id' => $fixture->id,
                'user_id' => $user->id,
                'changed_by_user_id' => $user->id,
                'old_home_score' => $oldHome,
                'old_away_score' => $oldAway,
                'new_home_score' => $homeScore,
                'new_away_score' => $awayScore,
                'action' => $wasNew ? 'created' : 'updated',
            ]);
        }

        return $prediction;
    }

    public function adminEditPrediction(Prediction $prediction, User $admin, ?int $homeScore, ?int $awayScore, string $reason): void
    {
        $quiniela = $prediction->match->quiniela;

        if ($admin->id !== $quiniela->owner_id) {
            throw new \RuntimeException('Solo el administrador puede editar predicciones de otros usuarios.');
        }

        $this->validateScoreRange($homeScore);
        $this->validateScoreRange($awayScore);

        $oldHome = $prediction->home_score;
        $oldAway = $prediction->away_score;

        $prediction->update([
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ]);

        PredictionLog::create([
            'prediction_id' => $prediction->id,
            'match_id' => $prediction->match_id,
            'user_id' => $prediction->user_id,
            'changed_by_user_id' => $admin->id,
            'old_home_score' => $oldHome,
            'old_away_score' => $oldAway,
            'new_home_score' => $homeScore,
            'new_away_score' => $awayScore,
            'action' => 'admin_edit',
            'reason' => $reason,
        ]);
    }

    public function canEdit(Quiniela $quiniela, User $user): bool
    {
        if ($user->id === $quiniela->owner_id) {
            return true;
        }

        if ($quiniela->prediction_edit_deadline && $quiniela->prediction_edit_deadline->isPast()) {
            return false;
        }

        return true;
    }

    public function getProgress(Quiniela $quiniela, User $user): array
    {
        $total = $quiniela->matches()->count();
        $completed = Prediction::where('user_id', $user->id)
            ->whereIn('match_id', $quiniela->matches()->select('id'))
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->count();

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }

    public function canViewOtherPredictions(Quiniela $quiniela, User $user): bool
    {
        if ($user->id === $quiniela->owner_id) {
            return true;
        }

        if ($quiniela->prediction_edit_deadline && $quiniela->prediction_edit_deadline->isPast()) {
            return true;
        }

        if (! $quiniela->prediction_edit_deadline && $quiniela->matches()->where('is_completed', true)->exists()) {
            return true;
        }

        return false;
    }

    protected function validateScoreRange(?int $score): void
    {
        if ($score !== null && ($score < 0 || $score > 99)) {
            throw new \RuntimeException('El marcador debe estar entre 0 y 99.');
        }
    }
}
