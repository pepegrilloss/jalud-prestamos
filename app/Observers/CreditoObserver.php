<?php

namespace App\Observers;

use App\Models\Credito;
use App\Models\Cuota;
use Carbon\Carbon;

class CreditoObserver
{
    public function created(Credito $credito)
    {
        $this->generarCuotas($credito);
    }

    private function generarCuotas(Credito $credito)
    {
        $proposicion = $credito->proposicion;
        
        if (!$proposicion) {
            return;
        }

        // Obtener días feriados de Perú
        $feriadosData = [];
        try {
            $fechaInicio = Carbon::parse($credito->FechaGeneracion);
            $fechaFin = $fechaInicio->copy()->addDays($proposicion->NumeroCuotas);
            $annoInicio = $fechaInicio->year;
            $annoFin = $fechaFin->year;
            
            for ($anno = $annoInicio; $anno <= $annoFin; $anno++) {
                try {
                    $response = file_get_contents("https://date.nager.at/api/v3/PublicHolidays/{$anno}/PE");
                    $feriados = json_decode($response, true);
                    foreach ($feriados as $feriado) {
                        $feriadosData[$feriado['date']] = $feriado['localName'];
                    }
                } catch (\Exception $e) {
                    // Continuar sin feriados si falla la API
                }
            }
        } catch (\Exception $e) {
            // Continuar sin feriados
        }

        $fechaActual = Carbon::parse($credito->FechaGeneracion)->addDay();
        $numeroCuota = 0;
        $cuotasGeneradas = 0;
        $cuotasRequeridas = $proposicion->NumeroCuotas;

        // Generar TODAS las fechas (incluyendo domingos y feriados) hasta alcanzar cuotas requeridas
        while ($cuotasGeneradas < $cuotasRequeridas) {
            $esDomingo = $fechaActual->dayOfWeek == 0;
            $esFeriado = isset($feriadosData[$fechaActual->format('Y-m-d')]);

            // Si es domingo o feriado
            if ($esDomingo || $esFeriado) {
                $estado = $esDomingo ? Cuota::ESTADO_DOMINGO : Cuota::ESTADO_FERIADO;
                
                Cuota::create([
                    'CreditoID' => $credito->CreditoID,
                    'NumeroCuota' => 0, // No cuenta como cuota
                    'FechaVencimiento' => $fechaActual->format('Y-m-d'),
                    'MontoCuota' => 0.00,
                    'MontoCapital' => 0.00,
                    'MontoInteres' => 0.00,
                    'MontoPagado' => 0.00,
                    'SaldoPendiente' => 0.00,
                    'Estado' => $estado,
                    'DiasAtraso' => 0,
                    'MontoMora' => 0.00,
                    'FechaPago' => null,
                    'FechaCreacion' => now(),
                    'FechaModificacion' => null,
                    'Activo' => 1
                ]);
            } else {
                // Es un día normal - es una cuota real
                $numeroCuota++;

                Cuota::create([
                    'CreditoID' => $credito->CreditoID,
                    'NumeroCuota' => $numeroCuota,
                    'FechaVencimiento' => $fechaActual->format('Y-m-d'),
                    'MontoCuota' => $proposicion->MontoCuota,
                    'MontoCapital' => $proposicion->MontoTotal / $proposicion->NumeroCuotas,
                    'MontoInteres' => $proposicion->MontoInteres / $proposicion->NumeroCuotas,
                    'MontoPagado' => 0.00,
                    'SaldoPendiente' => $proposicion->MontoCuota,
                    'Estado' => Cuota::ESTADO_PENDIENTE,
                    'DiasAtraso' => 0,
                    'MontoMora' => 0.00,
                    'FechaPago' => null,
                    'FechaCreacion' => now(),
                    'FechaModificacion' => null,
                    'Activo' => 1
                ]);

                $cuotasGeneradas++;
            }

            $fechaActual->addDay();
        }
    }
}
