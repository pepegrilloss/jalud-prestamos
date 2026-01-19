<?php

namespace App\Observers;

use App\Models\Pago;
use App\Models\ProposicionCredito;

class PagoObserver
{
    /**
     * Handle the Pago "created" event.
     */
    public function created(Pago $pago): void
    {
        $this->actualizarSaldoPendiente($pago);
    }

    /**
     * Handle the Pago "updated" event.
     */
    public function updated(Pago $pago): void
    {
        $this->actualizarSaldoPendiente($pago);
    }

    /**
     * Handle the Pago "deleted" event.
     */
    public function deleted(Pago $pago): void
    {
        $this->actualizarSaldoPendiente($pago);
    }

    /**
     * Actualizar el SaldoPendiente de la ProposicionCredito asociada
     */
    private function actualizarSaldoPendiente(Pago $pago): void
    {
        // Obtener el crédito asociado
        $credito = $pago->credito;
        
        if ($credito && $credito->proposicion) {
            $proposicion = $credito->proposicion;
            
            // Calcular total de pagos activos
            $totalPagos = $credito->pagos()
                ->where('Activo', true)
                ->sum('MontoPagado');
            
            // Calcular saldo pendiente: Monto Total a Pagar - Total de Pagos
            $saldoPendiente = max(0, $proposicion->MontoTotalPagar - $totalPagos);
            
            // Actualizar el SaldoPendiente en ProposicionCredito
            $proposicion->update([
                'SaldoPendiente' => $saldoPendiente
            ]);
        }
    }
}
