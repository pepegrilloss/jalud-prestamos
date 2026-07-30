<?php

namespace App\Services;

use Carbon\Carbon;

class CreditoFechaService
{
    public static function calcularRangoPorCuotasLaborables(Carbon|string $fechaGeneracion, int $numeroCuotas, ?int $sedeId = null): array
    {
        $numeroCuotas = max(1, $numeroCuotas);
        $fechaActual = Carbon::parse($fechaGeneracion)->startOfDay()->addDay();
        $cuotasContadas = 0;
        $fechaInicio = null;
        $fechaVencimiento = null;

        while ($cuotasContadas < $numeroCuotas) {
            if (CalendarioLaboralService::esLaborable($fechaActual, $sedeId)) {
                $fechaInicio ??= $fechaActual->copy();
                $fechaVencimiento = $fechaActual->copy();
                $cuotasContadas++;
            }

            $fechaActual->addDay();
        }

        return [
            'FechaInicio' => $fechaInicio,
            'FechaVencimiento' => $fechaVencimiento,
        ];
    }
}
