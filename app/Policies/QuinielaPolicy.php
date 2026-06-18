<?php

namespace App\Policies;

use App\Models\Quiniela;
use App\Models\User;

class QuinielaPolicy
{
    public function view(User $user, Quiniela $quiniela): bool
    {
        return $user->id === $quiniela->owner_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Quiniela $quiniela): bool
    {
        return $user->id === $quiniela->owner_id;
    }

    public function delete(User $user, Quiniela $quiniela): bool
    {
        return $user->id === $quiniela->owner_id;
    }
}
