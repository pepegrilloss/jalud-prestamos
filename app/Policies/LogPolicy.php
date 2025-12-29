<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Log;

class LogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, Log $log): bool
    {
        return $user->hasRole('super_admin');
    }

    public function create(User $user): bool
    {
        return false; // No permitir crear logs manualmente
    }

    public function update(User $user, Log $log): bool
    {
        return false; // No permitir editar logs
    }

    public function delete(User $user, Log $log): bool
    {
        return false; // No permitir eliminar logs
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('super_admin'); // Solo Super admin puede eliminar múltiples
    }
}
