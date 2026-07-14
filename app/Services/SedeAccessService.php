<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class SedeAccessService
{
    /**
     * Resuelve una sede para reportes sin permitir que un parametro URL amplie el alcance del usuario.
     */
    public function resolveReportSedeId(User $user, mixed $sedeParam): ?int
    {
        $puedeVerTodas = $user->esAdmin() || $user->puedeVerTodasLasSedes();

        if ($sedeParam === null) {
            return $user->getEffectiveSedeId();
        }

        $sedeParam = (string) $sedeParam;

        if ($sedeParam === '' || $sedeParam === '0' || strtolower($sedeParam) === 'todas') {
            if ($puedeVerTodas) {
                return null;
            }

            return $user->getEffectiveSedeId();
        }

        if (!ctype_digit($sedeParam) || (int) $sedeParam <= 0) {
            throw new AuthorizationException('La sede solicitada no es valida.');
        }

        $sedeId = (int) $sedeParam;

        if ($puedeVerTodas || $sedeId === $user->getEffectiveSedeId()) {
            return $sedeId;
        }

        throw new AuthorizationException('No tiene permiso para consultar informacion de otra sede.');
    }
}
