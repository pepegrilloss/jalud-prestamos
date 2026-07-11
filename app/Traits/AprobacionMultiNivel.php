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
     * Verifica si el usuario es Gerencia (nivel con Orden 1)
     */
    public function esGerencia(): bool
    {
        $nivelUsuario = $this->getNivelAprobacionActivo();
        if (!$nivelUsuario || !$nivelUsuario->nivelAprobacion) {
            return false;
        }
        return $nivelUsuario->nivelAprobacion->Orden === 1;
    }

    /**
     * Verifica si el rango del nivel activo del usuario cubre el monto indicado.
     * Regla: cualquier nivel cuyo rango contenga el monto puede aprobar.
     */
    public function puedeAprobarPorMonto(float $monto): bool
    {
        if ($this->esAdmin() || $this->hasRole('admin') || $this->puedeVerTodasLasSedes()) {
            return true;
        }

        $nivelUsuario = $this->getNivelAprobacionActivo();
        if (!$nivelUsuario || !$nivelUsuario->nivelAprobacion) {
            return false;
        }

        $nivel = $nivelUsuario->nivelAprobacion;
        return (float) $nivel->MontoMinimo <= $monto
            && (float) $nivel->MontoMaximo >= $monto;
    }

    /**
     * Verifica si el usuario puede aprobar una aprobación pendiente.
     * Se basa en que el rango del nivel del usuario cubra el monto de la proposición.
     */
    public function puedeAprobareAprobacion(AprobacionProposicion $aprobacion): bool
    {
        if ($this->hasRole(\BezhanSalleh\FilamentShield\Support\Utils::getSuperAdminName())) {
            return true;
        }

        if ($aprobacion->Estado !== 'PENDIENTE') {
            return false;
        }

        $monto = (float) ($aprobacion->proposicion?->MontoTotal ?? 0);
        return $this->puedeAprobarPorMonto($monto);
    }

    /**
     * Obtiene las aprobaciones pendientes cuyo monto está dentro del rango del nivel del usuario
     */
    public function aprobacionesPendientes()
    {
        $nivelUsuario = $this->getNivelAprobacionActivo();

        if (!$nivelUsuario || !$nivelUsuario->nivelAprobacion) {
            return collect([]);
        }

        $nivel = $nivelUsuario->nivelAprobacion;
        $montoMin = (float) $nivel->MontoMinimo;
        $montoMax = (float) $nivel->MontoMaximo;

        return AprobacionProposicion::where('Estado', 'PENDIENTE')
            ->whereHas('proposicion', function ($q) use ($montoMin, $montoMax) {
                $q->where('MontoTotal', '>=', $montoMin)
                  ->where('MontoTotal', '<=', $montoMax);
            })
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
     * Aprueba una proposición. Cualquier usuario cuyo nivel cubra el monto puede aprobarla.
     */
    public function aprobarProposicion(ProposicionCredito $proposicion, ?string $comentario = null): bool
    {
        if (!$this->puedeAprobarPorMonto((float) $proposicion->MontoTotal)) {
            return false;
        }

        $aprobacion = ($this->esAdmin() || $this->hasRole('admin') || $this->puedeVerTodasLasSedes())
            ? $proposicion->aprobaciones()->withoutGlobalScope('sede')->where('Estado', 'PENDIENTE')->first()
            : $proposicion->aprobaciones()->where('Estado', 'PENDIENTE')->first();

        if (!$aprobacion) {
            return false;
        }

        if (!$aprobacion->aprobar($this, $comentario)) {
            return false;
        }

        $proposicion->actualizarEstadoSegunAprobaciones();

        return true;
    }

    /**
     * Rechaza una proposición. Cualquier usuario cuyo nivel cubra el monto puede rechazarla.
     */
    public function rechazarProposicion(ProposicionCredito $proposicion, string $comentario): bool
    {
        if (!$this->puedeAprobarPorMonto((float) $proposicion->MontoTotal)) {
            return false;
        }

        $aprobacion = ($this->esAdmin() || $this->hasRole('admin') || $this->puedeVerTodasLasSedes())
            ? $proposicion->aprobaciones()->withoutGlobalScope('sede')->where('Estado', 'PENDIENTE')->first()
            : $proposicion->aprobaciones()->where('Estado', 'PENDIENTE')->first();

        if (!$aprobacion) {
            return false;
        }

        if (!$aprobacion->rechazar($this, $comentario)) {
            return false;
        }

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
