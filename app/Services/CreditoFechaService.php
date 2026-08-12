<?php

namespace App\Services;

use App\Models\Cuota;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CreditoFechaService
{
    public static function calcularRangoPorCuotasLaborables(Carbon|string $fechaGeneracion, int $numeroCuotas, ?int $sedeId = null): array
    {
        $cronograma = self::generarCronogramaPorCuotasLaborables($fechaGeneracion, $numeroCuotas, $sedeId);

        return [
            'FechaInicio' => $cronograma['FechaInicio'],
            'FechaVencimiento' => $cronograma['FechaVencimiento'],
        ];
    }

    public static function generarCronogramaPorCuotasLaborables(Carbon|string $fechaGeneracion, int $numeroCuotas, ?int $sedeId = null): array
    {
        $numeroCuotas = max(1, $numeroCuotas);
        $fechaActual = Carbon::parse($fechaGeneracion)->startOfDay()->addDay();
        $cuotasContadas = 0;
        $fechaInicio = null;
        $fechaVencimiento = null;
        $filas = [];

        while ($cuotasContadas < $numeroCuotas) {
            if (CalendarioLaboralService::esLaborable($fechaActual, $sedeId)) {
                $fechaInicio ??= $fechaActual->copy();
                $fechaVencimiento = $fechaActual->copy();
                $cuotasContadas++;

                $filas[] = [
                    'NumeroCuota' => $cuotasContadas,
                    'FechaVencimiento' => $fechaActual->toDateString(),
                    'Estado' => Cuota::ESTADO_NORMAL,
                ];
            } else {
                $filas[] = [
                    'NumeroCuota' => 0,
                    'FechaVencimiento' => $fechaActual->toDateString(),
                    'Estado' => $fechaActual->isSunday()
                        ? Cuota::ESTADO_DOMINGO
                        : Cuota::ESTADO_FERIADO,
                ];
            }

            $fechaActual->addDay();
        }

        return [
            'FechaInicio' => $fechaInicio,
            'FechaVencimiento' => $fechaVencimiento,
            'filas' => $filas,
        ];
    }

    public static function validarCatalogoFeriados(Carbon|string $fechaGeneracion, int $numeroCuotas): void
    {
        $inicio = Carbon::parse($fechaGeneracion)->startOfDay();
        $cuotas = max(1, $numeroCuotas);
        $finEstimado = $inicio->copy()->addDays((int) ceil($cuotas * 7 / 6) + 31);

        for ($anio = $inicio->year; $anio <= $finEstimado->year; $anio++) {
            if (FeriadoService::obtenerFeriados($anio) !== []) {
                continue;
            }

            throw ValidationException::withMessages([
                'NumeroCuotas' => "No existe un calendario nacional de feriados disponible para {$anio}. ".
                    "Ejecute php artisan feriados:sync {$anio} antes de generar o modificar creditos.",
            ]);
        }
    }
}
