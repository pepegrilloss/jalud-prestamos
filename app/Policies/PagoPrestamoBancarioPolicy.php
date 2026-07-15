<?php

namespace App\Policies;

use App\Models\PagoPrestamoBancario;
use App\Models\User;

class PagoPrestamoBancarioPolicy
{
    private function accesoGerencia(User $user): bool { return $user->puedeAccederAGerencia(); }

    public function viewAny(User $user): bool { return $this->accesoGerencia($user); }
    public function view(User $user, PagoPrestamoBancario $pago): bool { return $this->accesoGerencia($user); }
    public function create(User $user): bool { return $this->accesoGerencia($user); }
    public function update(User $user, PagoPrestamoBancario $pago): bool { return false; }
    public function delete(User $user, PagoPrestamoBancario $pago): bool { return false; }
}
