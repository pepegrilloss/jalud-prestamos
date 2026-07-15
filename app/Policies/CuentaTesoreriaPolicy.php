<?php

namespace App\Policies;

use App\Models\CuentaTesoreria;
use App\Models\User;

class CuentaTesoreriaPolicy
{
    private function accesoGerencia(User $user): bool
    {
        return $user->puedeAccederAGerencia();
    }

    public function viewAny(User $user): bool { return $this->accesoGerencia($user); }
    public function view(User $user, CuentaTesoreria $cuenta): bool { return $this->accesoGerencia($user); }
    public function create(User $user): bool { return $this->accesoGerencia($user); }
    public function update(User $user, CuentaTesoreria $cuenta): bool { return $this->accesoGerencia($user); }
    public function delete(User $user, CuentaTesoreria $cuenta): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
}
