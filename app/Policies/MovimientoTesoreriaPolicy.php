<?php

namespace App\Policies;

use App\Models\MovimientoTesoreria;
use App\Models\User;

class MovimientoTesoreriaPolicy
{
    private function accesoGerencia(User $user): bool
    {
        return $user->puedeAccederAGerencia();
    }

    public function viewAny(User $user): bool { return $this->accesoGerencia($user); }
    public function view(User $user, MovimientoTesoreria $movimiento): bool { return $this->accesoGerencia($user); }
    public function create(User $user): bool { return $this->accesoGerencia($user); }
    public function update(User $user, MovimientoTesoreria $movimiento): bool { return false; }
    public function delete(User $user, MovimientoTesoreria $movimiento): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
}
