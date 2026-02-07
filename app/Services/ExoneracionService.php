<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\SolicitudExoneracion;
use App\Models\TipoExoneracion;
use App\Models\Pago;
use Illuminate\Database\Eloquent\Model;

class ExoneracionService
{
    public function obtenerMontoDisponibleInteres(int $creditoID): float
    {
        $credito = Credito::with('proposicion')->find($creditoID);
        
        if (!$credito || !$credito->proposicion) {
            return 0;
        }

        $montoInteres = $credito->proposicion->MontoInteres ?? 0;
        
        $interesesExonerados = Pago::where('CreditoID', $creditoID)
            ->where('TipoConcepto', 'I')
            ->sum('MontoPagado');

        return max(0, (float) $montoInteres - (float) $interesesExonerados);
    }

    public function obtenerMontoDisponibleMora(int $creditoID): float
    {
        $moraAcumulada = Pago::where('CreditoID', $creditoID)
            ->where('EsMora', 1)
            ->sum('MontoPagado');

        $moraExonerada = Pago::where('CreditoID', $creditoID)
            ->where('TipoConcepto', 'M')
            ->sum('MontoPagado');

        return max(0, (float) $moraAcumulada - (float) $moraExonerada);
    }

    public function esElegibleProntoPago(int $creditoID): bool
    {
        $cuotasAtrasadas = \App\Models\Cuota::where('CreditoID', $creditoID)
            ->where('DiasAtraso', '>', 0)
            ->count();

        return $cuotasAtrasadas === 0;
    }

    public function existeSolicitudPendiente(int $creditoID, int $tipoExoneracionID): bool
    {
        return SolicitudExoneracion::where('CreditoID', $creditoID)
            ->where('TipoExoneracionID', $tipoExoneracionID)
            ->whereIn('Estado', ['PENDIENTE', 'APROBADO'])
            ->where('Activo', 1)
            ->exists();
    }

    public function crearSolicitud(int $creditoID, int $tipoExoneracionID, float $montoExonerado, string $comentario, int $userSolicitanteID, ?int $nivelAprobacionID = null): SolicitudExoneracion
    {
        $tipoExoneracion = TipoExoneracion::find($tipoExoneracionID);
        $montoDisponible = 0;

        switch ($tipoExoneracion->Codigo) {
            case 'I':
                $montoDisponible = $this->obtenerMontoDisponibleInteres($creditoID);
                break;
            case 'M':
                $montoDisponible = $this->obtenerMontoDisponibleMora($creditoID);
                break;
            case 'P':
                $cuota = \App\Models\Cuota::where('CreditoID', $creditoID)
                    ->where('Estado', 'PENDIENTE')
                    ->orderBy('NumeroCuota', 'desc')
                    ->first();
                $montoDisponible = $cuota?->MontoCuota ?? 0;
                break;
        }

        return SolicitudExoneracion::create([
            'CreditoID' => $creditoID,
            'TipoExoneracionID' => $tipoExoneracionID,
            'MontoDisponible' => $montoDisponible,
            'MontoExonerado' => $montoExonerado,
            'Comentario' => $comentario,
            'Estado' => 'PENDIENTE',
            'UserSolicitanteID' => $userSolicitanteID,
            'NivelAprobacionRequerido' => $nivelAprobacionID,
            'Activo' => 1,
        ]);
    }
}
