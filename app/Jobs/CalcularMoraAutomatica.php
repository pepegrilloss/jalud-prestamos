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

    public $fecha;

    public function __construct($fecha = null)
    {
        $this->fecha = $fecha ? \Carbon\Carbon::parse($fecha)->toDateString() : today()->toDateString();
    }

    public function handle(): void
    {
        $fecha = $this->fecha ? \Carbon\Carbon::parse($this->fecha) : today();
        \Log::info('[JOB] CalcularMoraAutomatica: Iniciando cálculo de moras', ['fecha' => $fecha->toDateString()]);
        
        // Validar si es domingo o feriado - NO calcular mora en esos días
        if ($fecha->dayOfWeek == 0) { // 0 = Domingo
            \Log::info('[JOB] No se calcula mora - Día es DOMINGO', ['fecha' => $fecha->toDateString()]);
            return;
        }

        // Obtener días feriados de Perú
        $feriadosData = [];
        try {
            $anno = $fecha->year;
            $response = file_get_contents("https://date.nager.at/api/v3/PublicHolidays/{$anno}/PE");
            $feriados = json_decode($response, true);
            if ($feriados) {
                foreach ($feriados as $feriado) {
                    $feriadosData[$feriado['date']] = $feriado['localName'];
                }
            }
        } catch (\Exception $e) {
            \Log::warning('[JOB] No se pudieron obtener feriados de la API', ['error' => $e->getMessage()]);
        }

        // Validar si es feriado
        if (isset($feriadosData[$fecha->format('Y-m-d')])) {
            \Log::info('[JOB] No se calcula mora - Día es FERIADO', [
                'fecha' => $fecha->toDateString(),
                'feriado' => $feriadosData[$fecha->format('Y-m-d')]
            ]);
            return;
        }
        
        try {
            // Obtener todos los créditos que:
            // 1. Han vencido (FechaVencimiento <= hoy)
            // 2. Tienen saldo pendiente
            // 3. Están activos
            $creditosVencidos = Credito::where('Activo', 1)
                ->whereDate('FechaVencimiento', '<=', now())
                ->with(['proposicion.cliente.tasaMora', 'cuotas' => fn($q) => $q->where('Estado', '!=', 'PAGADA')])
                ->get();

            \Log::info('[JOB] Créditos vencidos encontrados: ' . $creditosVencidos->count());

            $morasCreadas = 0;

            foreach ($creditosVencidos as $credito) {
                \Log::debug('[JOB] Procesando crédito: ' . $credito->CreditoID);
                
                // Verificar si ya existe mora registrada para la fecha especificada
                $moraHoy = Mora::where('CreditoID', $credito->CreditoID)
                    ->whereDate('FechaMora', $fecha->toDateString())
                    ->exists();

                if ($moraHoy) {
                    \Log::debug('[JOB] Crédito ' . $credito->CreditoID . ': Ya tiene mora registrada para ' . $fecha->toDateString());
                    continue; // Ya se calculó para esa fecha
                }

                // Obtener saldo pendiente de la proposición de crédito
                $saldoPendiente = $credito->proposicion?->SaldoPendiente ?? 0;

                if ($saldoPendiente <= 0) {
                    \Log::debug('[JOB] Crédito ' . $credito->CreditoID . ': Saldo pendiente <= 0: ' . number_format($saldoPendiente, 2));
                    continue; // No hay saldo, no calcular mora
                }

                // Obtener cliente a través de proposicion
                $cliente = $credito->proposicion?->cliente;
                if (!$cliente) {
                    \Log::warning('[JOB] Crédito ' . $credito->CreditoID . ': No tiene cliente asociado');
                    continue; // No hay cliente asociado
                }

                // Obtener porcentaje de mora del cliente
                $porcentajeMora = $cliente->tasaMora?->Porcentaje ?? 0;

                if ($porcentajeMora <= 0) {
                    \Log::warning('[JOB] Crédito ' . $credito->CreditoID . ': Cliente sin tasa de mora');
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
                $moraNueva = Mora::create([
                    'CreditoID' => $credito->CreditoID,
                    'FechaMora' => $fecha,
                    'SaldoPendiente' => $saldoPendiente,
                    'PorcentajeMora' => $porcentajeMora,
                    'MontoMora' => $montoMora,
                    'MoraAcumulada' => $moraAcumulada,
                ]);

                $morasCreadas++;

                // Log para auditoría
                \Log::info('[JOB] Mora calculada', [
                    'CreditoID' => $credito->CreditoID,
                    'ClienteDNI' => $cliente->DNI,
                    'Fecha' => $fecha->toDateString(),
                    'SaldoPendiente' => $saldoPendiente,
                    'Porcentaje' => $porcentajeMora,
                    'MontoMora' => $montoMora,
                    'MoraAcumulada' => $moraAcumulada,
                ]);
            }

            \Log::info('[JOB] CalcularMoraAutomatica completado. Moras creadas: ' . $morasCreadas, ['fecha' => $fecha->toDateString()]);
            
        } catch (\Exception $e) {
            \Log::error('[JOB] Error en CalcularMoraAutomatica', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
