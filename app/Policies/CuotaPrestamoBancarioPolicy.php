<?php

namespace App\Policies;

use App\Models\CuotaPrestamoBancario;
use App\Models\User;

class CuotaPrestamoBancarioPolicy
{
    private function accesoGerencia(User $user): bool { return $user->puedeAccederAGerencia(); }

    public function viewAny(User $user): bool { return $this->accesoGerencia($user); }
    public function view(User $user, CuotaPrestamoBancario $cuota): bool { return $this->accesoGerencia($user); }
    public function update(User $user, CuotaPrestamoBancario $cuota): bool { return false; }
    public function delete(User $user, CuotaPrestamoBancario $cuota): bool { return false; }
}
