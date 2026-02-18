<?php

namespace App\Jobs;

use App\Models\Credito;
use App\Models\Mora;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalcularMoraAutomatica implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Obtener todos los créditos que:
        // 1. Han vencido (FechaVencimiento <= hoy)
        // 2. Tienen saldo pendiente
        // 3. Están activos
        $creditosVencidos = Credito::where('Activo', 1)
            ->whereDate('FechaVencimiento', '<=', now())
            ->with(['proposicion.cliente.tasaMora', 'cuotas' => fn($q) => $q->where('Estado', '!=', 'PAGADA')])
            ->get();

        $hoy = today();

        foreach ($creditosVencidos as $credito) {
            // Verificar si ya existe mora registrada para hoy
            $moraHoy = Mora::where('CreditoID', $credito->CreditoID)
                ->whereDate('FechaMora', $hoy)
                ->exists();

            if ($moraHoy) {
                continue; // Ya se calculó hoy
            }

            // Obtener saldo pendiente de la proposición de crédito
            $saldoPendiente = $credito->proposicion?->SaldoPendiente ?? 0;

            if ($saldoPendiente <= 0) {
                continue; // No hay saldo, no calcular mora
            }

            // Obtener cliente a través de proposicion
            $cliente = $credito->proposicion?->cliente;
            if (!$cliente) {
                continue; // No hay cliente asociado
            }

            // Obtener porcentaje de mora del cliente
            $porcentajeMora = $cliente->tasaMora?->Porcentaje ?? 0;

            if ($porcentajeMora <= 0) {
                continue; // No tiene tasa de mora configurada
            }

            // Calcular mora diaria
            // Mora = SaldoPendiente * (Porcentaje / 100)
            $montoMora = $saldoPendiente * ($porcentajeMora / 100);

            // Obtener mora acumulada anterior
            $moraAnterior = Mora::where('CreditoID', $credito->CreditoID)
                ->orderBy('FechaMora', 'desc')
                ->first();

            $moraAcumulada = ($moraAnterior?->MoraAcumulada ?? 0) + $montoMora;

            // Registrar la mora del día
            Mora::create([
                'CreditoID' => $credito->CreditoID,
                'FechaMora' => $hoy,
                'SaldoPendiente' => $saldoPendiente,
                'PorcentajeMora' => $porcentajeMora,
                'MontoMora' => $montoMora,
                'MoraAcumulada' => $moraAcumulada,
            ]);

            // Log para auditoría
            \Log::info("Mora calculada", [
                'CreditoID' => $credito->CreditoID,
                'ClienteDNI' => $cliente->DNI,
                'SaldoPendiente' => $saldoPendiente,
                'Porcentaje' => $porcentajeMora,
                'MontoMora' => $montoMora,
                'MoraAcumulada' => $moraAcumulada,
            ]);
        }
    }
}
