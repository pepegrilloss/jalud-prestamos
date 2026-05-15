<?php

namespace App\Observers;

use App\Models\Pago;
use App\Models\ProposicionCredito;
use App\Services\FondoSedeService;
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
    }

    public function deleted(Pago $pago): void
    {
        if (!$pago->EsMora) {
            $this->actualizarSaldoPendiente($pago);
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
