<?php

namespace App\Console\Commands;

use App\Models\Credito;
use App\Models\Mora;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalcularMoraRetroactiva extends Command
{
    protected $signature = 'mora:retroactiva';
    protected $description = 'Calcula mora retroactivamente desde la fecha de vencimiento hasta hoy';

    public function handle(): int
    {
        $this->info("Calculando mora retroactivamente para todos los créditos vencidos...\n");
        $this->info("(NO se calcula mora en domingos ni feriados)\n");

        // Obtener días feriados de Perú para los años en rango
        $feriadosData = $this->obtenerFeriados();

        // Obtener créditos vencidos que aún tengan saldo
        $creditosVencidos = Credito::where('Activo', 1)
            ->whereDate('FechaVencimiento', '<', today())
            ->with(['proposicion.cliente.tasaMora', 'cuotas' => fn($q) => $q->where('Estado', '!=', 'PAGADA')])
            ->get();

        $morasRegistradas = 0;
        $morasTotales = 0;
        $diasOmitidos = 0;

        foreach ($creditosVencidos as $credito) {
            $cliente = $credito->proposicion?->cliente;
            if (!$cliente) continue;

            $porcentajeMora = $cliente->tasaMora?->Porcentaje ?? 0;
            if ($porcentajeMora <= 0) continue;

            // Para cada día desde el vencimiento hasta HOY
            $fecha = Carbon::createFromFormat('Y-m-d', $credito->FechaVencimiento->format('Y-m-d'));
            
            // Empezar desde el día después del vencimiento
            $fecha = $fecha->addDay();
            $hoy = today();

            while ($fecha <= $hoy) {
                // VALIDAR: NO CALCULAR EN DOMINGO
                if ($fecha->dayOfWeek == 0) {
                    $this->line("⊘ Crédito {$credito->CreditoID} - Fecha: {$fecha->format('d/m/Y')} - DOMINGO (omitido)");
                    $diasOmitidos++;
                    $fecha = $fecha->addDay();
                    continue;
                }

                // VALIDAR: NO CALCULAR EN FERIADO
                if (isset($feriadosData[$fecha->format('Y-m-d')])) {
                    $this->line("⊘ Crédito {$credito->CreditoID} - Fecha: {$fecha->format('d/m/Y')} - FERIADO ({$feriadosData[$fecha->format('Y-m-d')]}) (omitido)");
                    $diasOmitidos++;
                    $fecha = $fecha->addDay();
                    continue;
                }

                // Verificar si ya existe mora registrada para este día
                $moraExiste = Mora::where('CreditoID', $credito->CreditoID)
                    ->whereDate('FechaMora', $fecha)
                    ->exists();

                if ($moraExiste) {
                    $fecha = $fecha->addDay();
                    continue;
                }

                // Obtener saldo pendiente de la proposición de crédito
                $saldoPendiente = $credito->proposicion?->SaldoPendiente ?? 0;

                if ($saldoPendiente <= 0) {
                    $fecha = $fecha->addDay();
                    continue;
                }

                // Calcular mora diaria
                $montoMora = $saldoPendiente * ($porcentajeMora / 100);

                // Obtener mora acumulada anterior
                $moraAnterior = Mora::where('CreditoID', $credito->CreditoID)
                    ->orderBy('FechaMora', 'desc')
                    ->first();

                $moraAcumulada = ($moraAnterior?->MoraAcumulada ?? 0) + $montoMora;

                // Registrar la mora
                Mora::create([
                    'CreditoID' => $credito->CreditoID,
                    'FechaMora' => $fecha,
                    'SaldoPendiente' => $saldoPendiente,
                    'PorcentajeMora' => $porcentajeMora,
                    'MontoMora' => $montoMora,
                    'MoraAcumulada' => $moraAcumulada,
                ]);

                $morasRegistradas++;
                $morasTotales += $montoMora;

                $this->line("✓ Crédito {$credito->CreditoID} - Fecha: {$fecha->format('d/m/Y')} - Mora: {$montoMora} - Acumulada: {$moraAcumulada}");

                $fecha = $fecha->addDay();
            }
        }

        $this->info("\n✅ Cálculo retroactivo completado!");
        $this->info("   Moras registradas: {$morasRegistradas}");
        $this->info("   Días omitidos (domingos/feriados): {$diasOmitidos}");
        $this->info("   Monto total: S/. " . number_format($morasTotales, 2));

        return 0;
    }

    /**
     * Obtener feriados de Perú de los últimos años y los próximos
     */
    private function obtenerFeriados(): array
    {
        $feriadosData = [];
        $annoActual = now()->year;

        // Obtener feriados desde hace 2 años hasta 2 años en el futuro
        for ($anno = $annoActual - 2; $anno <= $annoActual + 2; $anno++) {
            try {
                $response = file_get_contents("https://date.nager.at/api/v3/PublicHolidays/{$anno}/PE");
                $feriados = json_decode($response, true);
                if ($feriados) {
                    foreach ($feriados as $feriado) {
                        $feriadosData[$feriado['date']] = $feriado['localName'];
                    }
                }
            } catch (\Exception $e) {
                $this->warn("⚠️  No se pudieron obtener feriados para el año {$anno}");
            }
        }

        return $feriadosData;
    }
}
