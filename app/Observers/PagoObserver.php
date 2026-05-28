<?php

namespace App\Observers;

use App\Models\Pago;
use App\Models\ProposicionCredito;
use App\Services\FondoSedeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PagoObserver
{
    public function created(Pago $pago): void
    {
        if (!$pago->EsMora) {
            $this->actualizarSaldoPendiente($pago);
        }
    }

    public function updated(Pago $pago): void
    {
        if (!$pago->EsMora) {
            $this->actualizarSaldoPendiente($pago);
        }

        if ($pago->wasChanged('EstadoTraslado')) {
            $credito = $pago->credito;
            if ($credito && $credito->EstatusCreditoFinal === 'SALDADO') {
                $proposicion = $credito->proposicion;
                if ($proposicion) {
                    $saldoActual = (float) DB::table('ProposicionCredito')
                        ->where('ProposicionCreditoID', $proposicion->ProposicionCreditoID)
                        ->value('SaldoPendiente');
                    if ($saldoActual > 0) {
                        $credito->update([
                            'EstatusCreditoFinal' => 'ACTIVO',
                            'FechaSaldamiento' => null,
                        ]);
                        Log::info('PagoObserver: Crédito revertido de SALDADO a ACTIVO por traslado de pago', [
                            'CreditoID' => $credito->CreditoID,
                            'PagoID' => $pago->PagoID,
                            'NuevoSaldo' => $saldoActual,
                        ]);
                    }
                }
            }
        }
    }

    public function deleted(Pago $pago): void
    {
        if (!$pago->EsMora) {
            $this->actualizarSaldoPendiente($pago);
        }

        // Si el crédito estaba SALDADO pero el saldo pendiente ya no es 0, revertir estado
        $credito = $pago->credito;
        if ($credito && $credito->EstatusCreditoFinal === 'SALDADO') {
            $proposicion = $credito->proposicion;
            if ($proposicion) {
                $saldoActual = (float) DB::table('ProposicionCredito')
                    ->where('ProposicionCreditoID', $proposicion->ProposicionCreditoID)
                    ->value('SaldoPendiente');

                if ($saldoActual > 0) {
                    $credito->update([
                        'EstatusCreditoFinal' => 'ACTIVO',
                        'FechaSaldamiento' => null,
                    ]);
                    Log::info('PagoObserver: Crédito revertido de SALDADO a ACTIVO por borrado de pago', [
                        'CreditoID' => $credito->CreditoID,
                        'PagoID' => $pago->PagoID,
                        'NuevoSaldo' => $saldoActual,
                    ]);
                }
            }
        }

        if ($pago->SedeID && $pago->MontoPagado > 0) {
            try {
                app(FondoSedeService::class)->registrarReversionRecaudo(
                    $pago->SedeID,
                    $pago->MontoPagado,
                    $pago->PagoID,
                    auth()->id()
                );
            } catch (\Exception $e) {
                Log::warning('FondoSede: No se pudo revertir ingreso por borrado de pago', [
                    'PagoID' => $pago->PagoID,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function actualizarSaldoPendiente(Pago $pago): void
    {
        $credito = $pago->credito;

        if ($credito && $credito->proposicion) {
            \App\Services\SaldoPendienteService::recalcular(
                $credito->proposicion->ProposicionCreditoID
            );
        }
    }
}
