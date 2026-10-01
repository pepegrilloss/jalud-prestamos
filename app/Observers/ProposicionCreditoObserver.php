<?php

namespace App\Observers;

use App\Models\Credito;
use App\Models\Log;
use App\Models\ProposicionCredito;
use App\Models\User;
use App\Services\CreditoCronogramaService;
use App\Services\CreditoFechaService;
use Illuminate\Support\Facades\DB;

class ProposicionCreditoObserver
{
    public function updating(ProposicionCredito $proposicionCredito): void
    {
        if (! $proposicionCredito->isDirty('NumeroCuotas')) {
            return;
        }

        $proposicionId = $proposicionCredito->getKey()
            ?? $proposicionCredito->getOriginal('ProposicionCreditoID');
        $credito = Credito::withoutGlobalScope('sede')
            ->where('ProposicionCreditoID', $proposicionId)
            ->first();

        if ($credito) {
            CreditoFechaService::validarCatalogoFeriados(
                $credito->FechaGeneracion,
                (int) $proposicionCredito->NumeroCuotas
            );
        }
    }

    public function created(ProposicionCredito $proposicionCredito): void
    {
        if (! $proposicionCredito->CodigoCredito) {
            $proposicionCredito->update([
                'CodigoCredito' => ProposicionCredito::generarCodigoCredito(),
            ]);
        }

        DB::transaction(function () use ($proposicionCredito) {
            $proposicionCredito->refresh();
            $proposicionCredito->crearAprobacionesRequeridas();
        }, attempts: 3);

        try {
            $cliente = $proposicionCredito->cliente;
            $nombre = $cliente?->NombresApellidos ?? 'N/A';
            $monto = number_format((float) $proposicionCredito->MontoTotal, 2);
            $codigo = $proposicionCredito->CodigoCredito;

            User::notificarAdmin(
                'Nueva proposición de crédito',
                "{$codigo} — {$nombre} — S/ {$monto}",
                'heroicon-o-document-plus',
                $proposicionCredito->SedeID
            );
        } catch (\Exception $e) {
        }
    }

    /**
     * Handle the ProposicionCredito "updated" event.
     */
    public function updated(ProposicionCredito $proposicionCredito): void
    {
        if ($proposicionCredito->wasChanged('MontoTotal')) {
            DB::transaction(function () use ($proposicionCredito) {
                $proposicionCredito->refresh();
                $proposicionCredito->crearAprobacionesRequeridas();
            }, attempts: 3);
        }

        if ($proposicionCredito->wasChanged('NumeroCuotas')) {
            $this->sincronizarFechasCredito($proposicionCredito);
        }
    }

    private function sincronizarFechasCredito(ProposicionCredito $proposicion): void
    {
        $proposicionId = $proposicion->getKey()
            ?? $proposicion->getOriginal('ProposicionCreditoID');
        $credito = Credito::withoutGlobalScope('sede')
            ->where('ProposicionCreditoID', $proposicionId)
            ->first();

        if (! $credito) {
            return;
        }

        DB::transaction(function () use ($credito, $proposicion) {
            $credito = Credito::withoutGlobalScope('sede')
                ->where('CreditoID', $credito->CreditoID)
                ->lockForUpdate()
                ->firstOrFail();
            $rango = CreditoFechaService::calcularRangoPorCuotasLaborables(
                $credito->FechaGeneracion,
                (int) $proposicion->NumeroCuotas,
                $credito->SedeID
            );
            $anterior = [
                'NumeroCuotas' => (int) $proposicion->getOriginal('NumeroCuotas'),
                'FechaInicio' => $credito->FechaInicio?->toDateString(),
                'FechaVencimiento' => $credito->FechaVencimiento?->toDateString(),
            ];

            $credito->update([
                'FechaInicio' => $rango['FechaInicio']->toDateString(),
                'FechaVencimiento' => $rango['FechaVencimiento']->toDateString(),
            ]);
            $resumen = CreditoCronogramaService::sincronizarCuotasNumeradas(
                $credito,
                (int) $proposicion->NumeroCuotas,
                (float) $proposicion->MontoCuota
            );

            Log::registrar(
                'SYNC_CUOTAS',
                'Credito',
                $credito->CreditoID,
                $anterior,
                [
                    'NumeroCuotas' => (int) $proposicion->NumeroCuotas,
                    'FechaInicio' => $rango['FechaInicio']->toDateString(),
                    'FechaVencimiento' => $rango['FechaVencimiento']->toDateString(),
                    'Cronograma' => $resumen,
                ],
                $credito->SedeID
            );
        });
    }

    /**
     * Handle the ProposicionCredito "deleted" event.
     */
    public function deleted(ProposicionCredito $proposicionCredito): void
    {
        //
    }

    /**
     * Handle the ProposicionCredito "restored" event.
     */
    public function restored(ProposicionCredito $proposicionCredito): void
    {
        //
    }

    /**
     * Handle the ProposicionCredito "force deleted" event.
     */
    public function forceDeleted(ProposicionCredito $proposicionCredito): void
    {
        //
    }
}
