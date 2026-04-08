<?php

namespace App\Traits;

use App\Models\AprobacionProposicion;
use App\Models\ProposicionCredito;
use App\Models\NivelAprobacion;

trait AprobacionMultiNivel
{
    /**
     * Obtiene el nivel de aprobación activo del usuario
     */
    public function getNivelAprobacionActivo(): ?object
    {
        return $this->userNivelAprobaciones()
            ->where('Activo', true)
            ->with('nivelAprobacion')
            ->first();
    }

    /**
     * Verifica si el usuario puede aprobar una proposición
     */
    public function puedeAprobareAprobacion(AprobacionProposicion $aprobacion): bool
    {
        // Super admin puede aprobar todo
        if ($this->hasRole(\BezhanSalleh\FilamentShield\Support\Utils::getSuperAdminName())) {
            return true;
        }

        // Obtener el nivel de aprobación del usuario
        $nivelUsuario = $this->getNivelAprobacionActivo();
        if (!$nivelUsuario) {
            return false;
        }

        // El nivel debe coincidir exactamente con el de la aprobación
        return $nivelUsuario->NivelAprobacionID === $aprobacion->NivelAprobacionID;
    }

    /**
     * Obtiene todas las aprobaciones pendientes que puede realizar
     */
    public function aprobacionesPendientes()
    {
        $nivelUsuario = $this->getNivelAprobacionActivo();
        
        if (!$nivelUsuario) {
            return collect([]);
        }

        return AprobacionProposicion::where('NivelAprobacionID', $nivelUsuario->NivelAprobacionID)
            ->where('Estado', 'PENDIENTE')
            ->with(['proposicion', 'nivel'])
            ->get();
    }

    /**
     * Obtiene la cantidad de aprobaciones pendientes
     */
    public function countAprobacionesPendientes(): int
    {
        return $this->aprobacionesPendientes()->count();
    }

    /**
     * Aprueba una proposición en su nivel (o aprobando todo si es Gerencia/Orden 1)
     */
    public function aprobarProposicion(ProposicionCredito $proposicion, ?string $comentario = null): bool
    {
        $nivelActivo = $this->getNivelAprobacionActivo();
        if (!$nivelActivo) return false;

        $esGerencia = $nivelActivo->nivelAprobacion && $nivelActivo->nivelAprobacion->Orden === 1;

        if ($esGerencia) {
            // Gerencia aprueba TODOS los niveles pendientes en una sola acción
            $pendientes = $proposicion->aprobaciones()->where('Estado', 'PENDIENTE')->get();
            if ($pendientes->isEmpty()) return false;

            foreach ($pendientes as $aprobacion) {
                // Forzamos la aprobación omitiendo `puedeAprobareAprobacion` porque Gerencia manda
                $aprobacion->aprobar($this, $comentario);
            }
        } else {
            $aprobacion = $proposicion->aprobaciones()
                ->where('NivelAprobacionID', $nivelActivo->NivelAprobacionID)
                ->where('Estado', 'PENDIENTE')
                ->first();

            if (!$aprobacion || !$this->puedeAprobareAprobacion($aprobacion)) {
                return false;
            }

            if (!$aprobacion->aprobar($this, $comentario)) {
                return false;
            }
        }

        // Actualizar el estado de la proposición
        $proposicion->actualizarEstadoSegunAprobaciones();

        return true;
    }

    /**
     * Rechaza una proposición en su nivel (o el paso actual si es Gerencia/Orden 1)
     */
    public function rechazarProposicion(ProposicionCredito $proposicion, string $comentario): bool
    {
        $nivelActivo = $this->getNivelAprobacionActivo();
        if (!$nivelActivo) return false;

        $esGerencia = $nivelActivo->nivelAprobacion && $nivelActivo->nivelAprobacion->Orden === 1;

        if ($esGerencia) {
            // Gerencia rechaza la proposición tomando la aprobación que esté pendiente
            $aprobacion = $proposicion->obtenerProximaAprobacionPendiente();
            if (!$aprobacion) return false;

            $aprobacion->rechazar($this, $comentario);
        } else {
            $aprobacion = $proposicion->aprobaciones()
                ->where('NivelAprobacionID', $nivelActivo->NivelAprobacionID)
                ->where('Estado', 'PENDIENTE')
                ->first();

            if (!$aprobacion || !$this->puedeAprobareAprobacion($aprobacion)) {
                return false;
            }

            if (!$aprobacion->rechazar($this, $comentario)) {
                return false;
            }
        }

        // Actualizar el estado de la proposición
        $proposicion->actualizarEstadoSegunAprobaciones();

        return true;
    }

    /**
     * Obtiene el rango de montos que puede aprobar
     */
    public function getRangoMontosAprobables(): array
    {
        $nivel = $this->getNivelAprobacionActivo();
        
        if (!$nivel || !$nivel->nivelAprobacion) {
            return ['minimo' => 0, 'maximo' => 0];
        }

        return [
            'minimo' => $nivel->nivelAprobacion->MontoMinimo,
            'maximo' => $nivel->nivelAprobacion->MontoMaximo,
            'nombre' => $nivel->nivelAprobacion->Nombre,
        ];
    }
}
