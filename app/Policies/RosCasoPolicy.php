<?php

namespace App\Policies;

use App\Models\RosCaso;
use App\Models\User;

class RosCasoPolicy
{
    private function puedeAcceder(User $user): bool
    {
        return $user->puedeAccederACumplimientoSbs();
    }

    public function viewAny(User $user): bool
    {
        return $this->puedeAcceder($user);
    }

    public function view(User $user, RosCaso $caso): bool
    {
        return $this->puedeAcceder($user);
    }

    public function create(User $user): bool
    {
        return $this->puedeAcceder($user);
    }

    public function update(User $user, RosCaso $caso): bool
    {
        return $this->puedeAcceder($user);
    }

    public function delete(User $user, RosCaso $caso): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
