<?php

namespace App\Observers;

use App\Models\Pago;
use App\Models\ProposicionCredito;
use App\Models\User;
use App\Services\FondoSedeService;
use Illuminate\Support\Facades\Log;

class PagoObserver
{
    public function created(Pago $pago): void
    {
        $this->actualizarSaldoPendiente($pago);
        $this->notificarPago($pago, 'creado');
    }

    public function updated(Pago $pago): void
    {
        $this->actualizarSaldoPendiente($pago);
    }

    public function deleted(Pago $pago): void
    {
        $this->actualizarSaldoPendiente($pago);

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

        $this->notificarPago($pago, 'borrado');
    }

    private function actualizarSaldoPendiente(Pago $pago): void
    {
        $credito = $pago->credito;

        if ($credito && $credito->proposicion) {
            $saldoPendiente = ProposicionCredito::calcularSaldoPendiente(
                $credito->proposicion->ProposicionCreditoID
            );

            $credito->proposicion->update([
                'SaldoPendiente' => $saldoPendiente
            ]);
        }
    }

    private function notificarPago(Pago $pago, string $accion): void
    {
        try {
            $credito = $pago->credito;
            if (!$credito || !$credito->proposicion) return;

            $cliente = $credito->proposicion->cliente;
            $nombre = $cliente?->NombresApellidos ?? 'N/A';
            $monto = number_format((float) $pago->MontoPagado, 2);
            $codigo = $credito->proposicion->CodigoCredito ?? 'N/A';

            if ($accion === 'borrado') {
                User::notificarAdmin(
                    'Pago borrado',
                    "S/ {$monto} — {$nombre} — {$codigo} — por " . (auth()->user()?->name ?? 'Sistema'),
                    'heroicon-o-x-circle'
                );
            } else {
                User::notificarAdmin(
                    'Pago registrado',
                    "S/ {$monto} — {$nombre} — {$codigo}",
                    'heroicon-o-currency-dollar'
                );
            }
        } catch (\Exception $e) {
        }
    }
}
