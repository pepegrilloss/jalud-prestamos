<?php

namespace App\Observers;

use App\Models\Credito;
use App\Models\Cuota;
use App\Models\User;
use Carbon\Carbon;

class CreditoObserver
{
    public function created(Credito $credito)
    {
        $this->generarCuotas($credito);

        // Recalcular saldo pendiente al crear el crédito
        try {
            \App\Services\SaldoPendienteService::recalcular($credito->ProposicionCreditoID);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al recalcular saldo en CreditoObserver: ' . $e->getMessage());
        }

        try {
            $proposicion = $credito->proposicion;
            if ($proposicion) {
                $cliente = $proposicion->cliente;
                $nombre = $cliente?->NombresApellidos ?? 'N/A';
                $monto = number_format((float) $proposicion->MontoTotal, 2);
                $codigo = $proposicion->CodigoCredito;

                User::notificarAdmin(
                    'Crédito desembolsado',
                    "{$codigo} — {$nombre} — S/ {$monto}",
                    'heroicon-o-banknotes',
                    $proposicion->SedeID
                );
            }
        } catch (\Exception $e) {
        }
    }

    public function updated(Credito $credito)
    {
        if ($credito->wasChanged('Activo')) {
            try {
                \App\Services\SaldoPendienteService::recalcular($credito->ProposicionCreditoID);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error al recalcular saldo en CreditoObserver@updated: ' . $e->getMessage());
            }
        }
    }

    public function deleted(Credito $credito)
    {
        try {
            \App\Services\SaldoPendienteService::recalcular($credito->ProposicionCreditoID);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al recalcular saldo en CreditoObserver@deleted: ' . $e->getMessage());
        }
    }

    private function generarCuotas(Credito $credito)
    {
        $proposicion = $credito->proposicion;

        if (!$proposicion) {
            return;
        }

        // Verificar si las cuotas ya existen (evitar duplicados)
        $cuotasExistentes = Cuota::where('CreditoID', $credito->CreditoID)->count();
        if ($cuotasExistentes > 0) {
            return;
        }

        // Obtener días feriados de Perú
        $feriadosData = [];
        try {
            $fechaInicio = Carbon::parse($credito->FechaGeneracion);
            $fechaFin = $fechaInicio->copy()->addDays($proposicion->NumeroCuotas * 2);
            $annoInicio = $fechaInicio->year;
            $annoFin = $fechaFin->year;

            for ($anno = $annoInicio; $anno <= $annoFin; $anno++) {
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(5)->retry(2, 100)->get("https://date.nager.at/api/v3/PublicHolidays/{$anno}/PE");
                    $feriados = $response->json();
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

        $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
        $fechaCreacion = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();

        // --- CREAR CUOTA 0 (PAGO INICIAL) EL DÍA DE GENERACIÓN ---
        $fechaGeneracion = Carbon::parse($credito->FechaGeneracion);
        Cuota::create([
            'CreditoID' => $credito->CreditoID,
            'NumeroCuota' => 0,
            'FechaVencimiento' => $fechaGeneracion->format('Y-m-d'),
            'MontoCuota' => 0.00,
            'Estado' => 'PAGO_INICIAL',
            'DiasAtraso' => 0,
            'MontoMora' => 0.00,
            'FechaPago' => null,
            'FechaCreacion' => $fechaCreacion,
            'FechaModificacion' => null,
            'Activo' => 1,
            'SedeID' => $credito->SedeID
        ]);

        $fechaActual = $fechaGeneracion->copy()->addDay();
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
                $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
                $fechaCreacion = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();

                Cuota::create([
                    'CreditoID' => $credito->CreditoID,
                    'NumeroCuota' => 0, // No cuenta como cuota
                    'FechaVencimiento' => $fechaActual->format('Y-m-d'),
                    'MontoCuota' => 0.00,
                    'Estado' => $estado,
                    'DiasAtraso' => 0,
                    'MontoMora' => 0.00,
                    'FechaPago' => null,
                    'FechaCreacion' => $fechaCreacion,
                    'FechaModificacion' => null,
                    'Activo' => 1,
                    'SedeID' => $credito->SedeID
                ]);
            } else {
                // Es un día normal - es una cuota real
                $numeroCuota++;
                $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();
                $fechaCreacion = $fechaAbierta ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) : now();

                Cuota::create([
                    'CreditoID' => $credito->CreditoID,
                    'NumeroCuota' => $numeroCuota,
                    'FechaVencimiento' => $fechaActual->format('Y-m-d'),
                    'MontoCuota' => $proposicion->MontoCuota,
                    'Estado' => Cuota::ESTADO_NORMAL,
                    'DiasAtraso' => 0,
                    'MontoMora' => 0.00,
                    'FechaPago' => null,
                    'FechaCreacion' => $fechaCreacion,
                    'FechaModificacion' => null,
                    'Activo' => 1,
                    'SedeID' => $credito->SedeID
                ]);

                $cuotasGeneradas++;
            }

            $fechaActual->addDay();
        }
    }
}
