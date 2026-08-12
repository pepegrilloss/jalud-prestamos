<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as LaravelLog;

class CreditoFechaRecalculationService
{
    public static function recalcularSede(int $sedeId, string $motivo): array
    {
        $resultado = [
            'auditados' => 0,
            'corregidos' => 0,
            'errores' => 0,
        ];

        Credito::withoutGlobalScope('sede')
            ->where('Credito.SedeID', $sedeId)
            ->where('Credito.Activo', true)
            ->where('Credito.EstatusCreditoFinal', 'ACTIVO')
            ->whereHas('proposicion', fn ($query) => $query->where('SaldoPendiente', '>', 0))
            ->with('proposicion:ProposicionCreditoID,NumeroCuotas,MontoCuota')
            ->orderBy('CreditoID')
            ->chunkById(100, function ($creditos) use (&$resultado, $motivo) {
                foreach ($creditos as $credito) {
                    $resultado['auditados']++;

                    try {
                        if (self::recalcularCredito($credito, $motivo)) {
                            $resultado['corregidos']++;
                        }
                    } catch (\Throwable $e) {
                        $resultado['errores']++;
                        LaravelLog::error('No se pudo recalcular la fecha del credito por cambio de calendario.', [
                            'CreditoID' => $credito->CreditoID,
                            'SedeID' => $credito->SedeID,
                            'motivo' => $motivo,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }, 'CreditoID', 'CreditoID');

        return $resultado;
    }

    public static function recalcularAnio(int $anio, string $motivo): array
    {
        $sedes = Credito::withoutGlobalScope('sede')
            ->where('Activo', true)
            ->where('EstatusCreditoFinal', 'ACTIVO')
            ->whereYear('FechaGeneracion', '<=', $anio)
            ->whereYear('FechaVencimiento', '>=', $anio)
            ->distinct()
            ->pluck('SedeID')
            ->filter();

        $resultado = ['auditados' => 0, 'corregidos' => 0, 'errores' => 0];

        foreach ($sedes as $sedeId) {
            $parcial = self::recalcularSede((int) $sedeId, $motivo);
            foreach ($resultado as $clave => $valor) {
                $resultado[$clave] += $parcial[$clave];
            }
        }

        return $resultado;
    }

    private static function recalcularCredito(Credito $credito, string $motivo): bool
    {
        $numeroCuotas = (int) ($credito->proposicion?->NumeroCuotas ?: 1);
        $rango = CreditoFechaService::calcularRangoPorCuotasLaborables(
            $credito->FechaGeneracion,
            $numeroCuotas,
            $credito->SedeID
        );

        $inicioAnterior = $credito->FechaInicio?->toDateString();
        $vencimientoAnterior = $credito->FechaVencimiento?->toDateString();
        $inicioNuevo = $rango['FechaInicio']->toDateString();
        $vencimientoNuevo = $rango['FechaVencimiento']->toDateString();

        if ($inicioAnterior === $inicioNuevo && $vencimientoAnterior === $vencimientoNuevo) {
            return false;
        }

        DB::transaction(function () use (
            $credito,
            $inicioAnterior,
            $vencimientoAnterior,
            $inicioNuevo,
            $vencimientoNuevo,
            $motivo
        ) {
            Credito::withoutGlobalScope('sede')
                ->where('CreditoID', $credito->CreditoID)
                ->lockForUpdate()
                ->update([
                    'FechaInicio' => $inicioNuevo,
                    'FechaVencimiento' => $vencimientoNuevo,
                ]);

            Log::registrar(
                'RECALC_FECHA',
                'Credito',
                $credito->CreditoID,
                [
                    'FechaInicio' => $inicioAnterior,
                    'FechaVencimiento' => $vencimientoAnterior,
                ],
                [
                    'FechaInicio' => $inicioNuevo,
                    'FechaVencimiento' => $vencimientoNuevo,
                    'Motivo' => $motivo,
                ],
                $credito->SedeID
            );
        });

        return true;
    }
}
