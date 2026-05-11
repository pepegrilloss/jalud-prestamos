<?php

namespace App\Console\Commands;

use App\Models\Credito;
use App\Models\Mora;
use App\Models\ProposicionCredito;
use App\Models\CalendarioNoMoroso;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalcularMoraRetroactiva extends Command
{
    protected $signature = 'mora:retroactiva';
    protected $description = 'Calcula mora retroactivamente desde la fecha de vencimiento hasta hoy';

    public function handle(): int
    {
        $this->info("Calculando mora retroactivamente para todos los créditos vencidos...\n");
        $this->info("(NO se calcula mora en domingos, feriados ni fechas del calendario no moroso)\n");

        $feriadosData = $this->obtenerFeriados();

        $fechasNoMorosas = CalendarioNoMoroso::where('Activo', true)
            ->get()
            ->map(fn($item) => Carbon::parse($item->Fecha)->toDateString())
            ->toArray();

        $fechasExcluidas = array_unique(array_merge(array_keys($feriadosData), $fechasNoMorosas));

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

            $vencimientoEfectivo = Carbon::parse($credito->FechaVencimiento);

            while (in_array($vencimientoEfectivo->toDateString(), $fechasExcluidas)
                   || $vencimientoEfectivo->dayOfWeek == 0) {
                $vencimientoEfectivo->addDay();
            }

            $fecha = $vencimientoEfectivo->copy()->addDay();
            $hoy = today();

            while ($fecha <= $hoy) {
                if ($fecha->dayOfWeek == 0) {
                    $this->line("⊘ Crédito {$credito->CreditoID} - Fecha: {$fecha->format('d/m/Y')} - DOMINGO (omitido)");
                    $diasOmitidos++;
                    $fecha = $fecha->addDay();
                    continue;
                }

                $fechaStr = $fecha->format('Y-m-d');
                $esFeriadoNacional = isset($feriadosData[$fechaStr]);
                $esNoMoroso = in_array($fechaStr, $fechasNoMorosas);

                if ($esFeriadoNacional || $esNoMoroso) {
                    $motivo = $esFeriadoNacional ? "FERIADO ({$feriadosData[$fechaStr]})" : 'CALENDARIO NO MOROSO';
                    $this->line("⊘ Crédito {$credito->CreditoID} - Fecha: {$fecha->format('d/m/Y')} - {$motivo} (omitido)");
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
                    ? ProposicionCredito::calcularSaldoPendiente($credito->proposicion->ProposicionCreditoID)
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
                ]);

                $morasRegistradas++;
                $morasTotales += $montoMora;

                $this->line("✓ Crédito {$credito->CreditoID} - Fecha: {$fecha->format('d/m/Y')} - Mora: {$montoMora} - Acumulada: {$moraAcumulada}");

                $fecha = $fecha->addDay();
            }
        }

        $this->info("\n✅ Cálculo retroactivo completado!");
        $this->info("   Moras registradas: {$morasRegistradas}");
        $this->info("   Días omitidos (domingos/feriados/calendario no moroso): {$diasOmitidos}");
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
                $response = \Illuminate\Support\Facades\Http::timeout(5)->retry(2, 100)->get("https://date.nager.at/api/v3/PublicHolidays/{$anno}/PE");
                $feriados = $response->json();
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
