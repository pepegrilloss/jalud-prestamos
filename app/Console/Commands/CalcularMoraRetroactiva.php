<?php

namespace App\Console\Commands;

use App\Models\Credito;
use App\Services\MoraCalculationService;
use Illuminate\Console\Command;

class CalcularMoraRetroactiva extends Command
{
    protected $signature = 'mora:retroactiva';

    protected $description = 'Completa moras faltantes desde el vencimiento hasta hoy usando saldos historicos';

    public function handle(MoraCalculationService $moraService): int
    {
        $this->warn('Este comando modifica datos. Para auditar primero use mora:auditar-calendario.');

        if (! $this->confirm('¿Desea continuar con el calculo retroactivo?', false)) {
            return self::SUCCESS;
        }

        $morasRegistradas = 0;
        $montoTotal = 0.0;

        Credito::withoutGlobalScope('sede')
            ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'Credito.ProposicionCreditoID')
            ->where('Credito.Activo', 1)
            ->where('pc.SaldoPendiente', '>', 0)
            ->whereDate('Credito.FechaVencimiento', '<', today())
            ->select('Credito.*')
            ->chunkById(500, function ($creditos) use ($moraService, &$morasRegistradas, &$montoTotal) {
                foreach ($creditos as $credito) {
                    $resultado = $moraService->procesarCreditoHasta($credito, today());
                    $morasRegistradas += $resultado['creadas'];
                    $montoTotal = round($montoTotal + $resultado['monto'], 2);
                }
            }, 'Credito.CreditoID', 'CreditoID');

        $this->info("Moras registradas: {$morasRegistradas}");
        $this->info('Monto total: S/ '.number_format($montoTotal, 2));

        return self::SUCCESS;
    }
}
