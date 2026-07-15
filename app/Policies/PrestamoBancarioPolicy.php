<?php

namespace App\Policies;

use App\Models\PrestamoBancario;
use App\Models\User;

class PrestamoBancarioPolicy
{
    private function accesoGerencia(User $user): bool { return $user->puedeAccederAGerencia(); }

    public function viewAny(User $user): bool { return $this->accesoGerencia($user); }
    public function view(User $user, PrestamoBancario $prestamo): bool { return $this->accesoGerencia($user); }
    public function create(User $user): bool { return $this->accesoGerencia($user); }
    public function update(User $user, PrestamoBancario $prestamo): bool { return false; }
    public function delete(User $user, PrestamoBancario $prestamo): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
}
