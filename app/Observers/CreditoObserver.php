<?php

namespace App\Observers;

use App\Models\Credito;
use App\Models\Cuota;
use App\Models\User;
use App\Services\CalendarioLaboralService;
use Carbon\Carbon;

class CreditoObserver
{
    public function created(Credito $credito)
    {
        $this->generarCuotas($credito);

        try {
            \App\Services\SaldoPendienteService::recalcular($credito->ProposicionCreditoID);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al recalcular saldo en CreditoObserver: '.$e->getMessage());
        }

        try {
            $proposicion = $credito->proposicion;
            if ($proposicion) {
                $cliente = $proposicion->cliente;
                $nombre = $cliente?->NombresApellidos ?? 'N/A';
                $monto = number_format((float) $proposicion->MontoTotal, 2);
                $codigo = $proposicion->CodigoCredito;

                User::notificarAdmin(
                    'Credito desembolsado',
                    "{$codigo} - {$nombre} - S/ {$monto}",
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
                \Illuminate\Support\Facades\Log::error('Error al recalcular saldo en CreditoObserver@updated: '.$e->getMessage());
            }
        }
    }

    public function deleted(Credito $credito)
    {
        try {
            \App\Services\SaldoPendienteService::recalcular($credito->ProposicionCreditoID);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al recalcular saldo en CreditoObserver@deleted: '.$e->getMessage());
        }
    }

    private function generarCuotas(Credito $credito): void
    {
        $proposicion = $credito->proposicion;

        if (! $proposicion) {
            return;
        }

        if (Cuota::where('CreditoID', $credito->CreditoID)->exists()) {
            return;
        }

        $fechaCreacion = $this->fechaCreacion();
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
            'SedeID' => $credito->SedeID,
        ]);

        $fechaActual = $fechaGeneracion->copy()->addDay();
        $numeroCuota = 0;
        $cuotasGeneradas = 0;
        $cuotasRequeridas = $proposicion->NumeroCuotas;

        while ($cuotasGeneradas < $cuotasRequeridas) {
            if (! CalendarioLaboralService::esLaborable($fechaActual, $credito->SedeID)) {
                $estado = $fechaActual->dayOfWeek === Carbon::SUNDAY
                    ? Cuota::ESTADO_DOMINGO
                    : Cuota::ESTADO_FERIADO;

                Cuota::create([
                    'CreditoID' => $credito->CreditoID,
                    'NumeroCuota' => 0,
                    'FechaVencimiento' => $fechaActual->format('Y-m-d'),
                    'MontoCuota' => 0.00,
                    'Estado' => $estado,
                    'DiasAtraso' => 0,
                    'MontoMora' => 0.00,
                    'FechaPago' => null,
                    'FechaCreacion' => $this->fechaCreacion(),
                    'FechaModificacion' => null,
                    'Activo' => 1,
                    'SedeID' => $credito->SedeID,
                ]);
            } else {
                $numeroCuota++;

                Cuota::create([
                    'CreditoID' => $credito->CreditoID,
                    'NumeroCuota' => $numeroCuota,
                    'FechaVencimiento' => $fechaActual->format('Y-m-d'),
                    'MontoCuota' => $proposicion->MontoCuota,
                    'Estado' => Cuota::ESTADO_NORMAL,
                    'DiasAtraso' => 0,
                    'MontoMora' => 0.00,
                    'FechaPago' => null,
                    'FechaCreacion' => $this->fechaCreacion(),
                    'FechaModificacion' => null,
                    'Activo' => 1,
                    'SedeID' => $credito->SedeID,
                ]);

                $cuotasGeneradas++;
            }

            $fechaActual->addDay();
        }
    }

    private function fechaCreacion(): Carbon
    {
        $fechaAbierta = \App\Services\DateFieldResolver::getFechaAbierta();

        return $fechaAbierta
            ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second)
            : now();
    }
}
