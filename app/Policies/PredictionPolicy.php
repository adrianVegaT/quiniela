<?php

namespace App\Policies;

use App\Models\Prediction;
use App\Models\Quiniela;
use App\Models\User;

class PredictionPolicy
{
    public function view(User $user, Prediction $prediction): bool
    {
        if ($user->id === $prediction->user_id) {
            return true;
        }

        return $user->id === $prediction->match->quiniela->owner_id;
    }

    public function create(User $user, Quiniela $quiniela): bool
    {
        return $quiniela->participants()
            ->where('user_id', $user->id)
            ->whereNull('quiniela_user.deleted_at')
            ->exists();
    }

    public function update(User $user, Prediction $prediction): bool
    {
        if ($user->id === $prediction->match->quiniela->owner_id) {
            return true;
        }

        if ($user->id !== $prediction->user_id) {
            return false;
        }

        $fixture = $prediction->match;
        $quiniela = $fixture->quiniela;

        if ($fixture->match_date->isPast()) {
            return false;
        }

        if ($quiniela->prediction_edit_deadline && $quiniela->prediction_edit_deadline->isPast()) {
            return false;
        }

        return true;
    }

    public function adminEdit(User $user, Prediction $prediction): bool
    {
        return $user->id === $prediction->match->quiniela->owner_id;
    }
}
