<?php

namespace App\Console\Commands;

use App\Models\Credito;
use App\Models\Mora;
use App\Services\CalendarioLaboralService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalcularMoraRetroactiva extends Command
{
    protected $signature = 'mora:retroactiva';
    protected $description = 'Calcula mora retroactivamente desde la fecha de vencimiento hasta hoy';

    public function handle(): int
    {
        $this->info("Calculando mora retroactivamente para todos los creditos vencidos...\n");
        $this->info("(No se calcula mora en dias no laborables, salvo laborable forzado)\n");

        $morasRegistradas = 0;
        $morasTotales = 0;
        $diasOmitidos = 0;

        Credito::withoutGlobalScope('sede')
            ->where('Activo', 1)
            ->whereDate('FechaVencimiento', '<', today())
            ->whereIn('SedeID', \App\Models\Sede::where('Activo', true)->pluck('SedeID'))
            ->with(['proposicion.cliente.tasaMora'])
            ->chunkById(500, function ($creditos) use (&$morasRegistradas, &$morasTotales, &$diasOmitidos) {
                foreach ($creditos as $credito) {
                    [$registradas, $total, $omitidos] = $this->procesarCredito($credito);
                    $morasRegistradas += $registradas;
                    $morasTotales += $total;
                    $diasOmitidos += $omitidos;
                }
            }, 'CreditoID');

        $this->info("\nCalculo retroactivo completado");
        $this->info("   Moras registradas: {$morasRegistradas}");
        $this->info("   Dias omitidos: {$diasOmitidos}");
        $this->info("   Monto total: S/. " . number_format($morasTotales, 2));

        return 0;
    }

    private function procesarCredito(Credito $credito): array
    {
        $morasRegistradas = 0;
        $morasTotales = 0;
        $diasOmitidos = 0;

        $cliente = $credito->proposicion?->cliente;
        if (!$cliente) {
            return [0, 0, 0];
        }

        $porcentajeMora = $cliente->tasaMora?->Porcentaje ?? 0;
        if ($porcentajeMora <= 0) {
            return [0, 0, 0];
        }

        $vencimientoEfectivo = CalendarioLaboralService::siguienteDiaLaborable(
            Carbon::parse($credito->FechaVencimiento),
            $credito->SedeID
        );

        $fecha = $vencimientoEfectivo->copy()->addDay();
        $hoy = today();

        while ($fecha <= $hoy) {
            $motivoNoLaborable = CalendarioLaboralService::motivoNoLaborable($fecha, $credito->SedeID);

            if ($motivoNoLaborable) {
                $this->line("omitido Credito {$credito->CreditoID} - Fecha: {$fecha->format('d/m/Y')} - {$motivoNoLaborable}");
                $diasOmitidos++;
                $fecha = $fecha->addDay();
                continue;
            }

            $moraExiste = Mora::where('CreditoID', $credito->CreditoID)
                ->whereDate('FechaMora', $fecha)
                ->exists();

            if ($moraExiste) {
                $fecha = $fecha->addDay();
                continue;
            }

            $saldoPendiente = $credito->proposicion
                ? (float) ($credito->proposicion->SaldoPendiente ?? 0)
                : 0;

            if ($saldoPendiente <= 0) {
                $fecha = $fecha->addDay();
                continue;
            }

            $montoMora = $saldoPendiente * ($porcentajeMora / 100);

            $moraAnterior = Mora::where('CreditoID', $credito->CreditoID)
                ->orderBy('FechaMora', 'desc')
                ->first();

            $moraAcumulada = ($moraAnterior?->MoraAcumulada ?? 0) + $montoMora;

            Mora::create([
                'CreditoID' => $credito->CreditoID,
                'FechaMora' => $fecha,
                'SaldoPendiente' => $saldoPendiente,
                'PorcentajeMora' => $porcentajeMora,
                'MontoMora' => $montoMora,
                'MoraAcumulada' => $moraAcumulada,
                'SedeID' => $credito->SedeID,
            ]);

            $morasRegistradas++;
            $morasTotales += $montoMora;

            $this->line("ok Credito {$credito->CreditoID} - Fecha: {$fecha->format('d/m/Y')} - Mora: {$montoMora} - Acumulada: {$moraAcumulada}");

            $fecha = $fecha->addDay();
        }

        return [$morasRegistradas, $morasTotales, $diasOmitidos];
    }
}
