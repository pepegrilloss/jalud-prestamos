<?php

namespace App\Jobs;

use App\Models\Credito;
use App\Models\Mora;
use App\Models\CalendarioNoMoroso;
use App\Models\ProposicionCredito;
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

        // Validar contra el Calendario No Moroso - NO calcular mora si la fecha está registrada
        $fechaNoMorosa = CalendarioNoMoroso::where('Fecha', $fecha->toDateString())
            ->where('Activo', true)
            ->first();

        if ($fechaNoMorosa) {
            \Log::info('[JOB] No se calcula mora - Fecha está en Calendario No Moroso', [
                'fecha' => $fecha->toDateString(),
                'descripcion' => $fechaNoMorosa->Descripcion,
            ]);
            return;
        }

        try {
            // Precargar todas las fechas no morosas para optimizar el rendimiento (evita miles de consultas)
            $fechasNoMorosas = CalendarioNoMoroso::where('Activo', true)
                ->get()
                ->map(fn($item) => \Carbon\Carbon::parse($item->Fecha)->toDateString())
                ->toArray();

            // Obtener todos los créditos vencidos de TODAS las sedes activas.
            // Sin auth context, el global scope no filtra, así que usamos withoutGlobalScope + explicit scoping.
            $creditosVencidos = Credito::withoutGlobalScope('sede')
                ->where('Activo', 1)
                ->whereDate('FechaVencimiento', '<=', $fecha)
                ->whereIn('SedeID', \App\Models\Sede::where('Activo', true)->pluck('SedeID'))
                ->with(['proposicion.cliente.tasaMora', 'cuotas' => fn($q) => $q->where('Estado', '!=', 'PAGADA')])
                ->get();

            \Log::info('[JOB] Créditos vencidos encontrados: ' . $creditosVencidos->count());

            $morasCreadas = 0;

            foreach ($creditosVencidos as $credito) {
                \Log::debug('[JOB] Procesando crédito: ' . $credito->CreditoID);
                
                // --- LÓGICA DE VENCIMIENTO EFECTIVO ---
                $vencimientoEfectivo = \Carbon\Carbon::parse($credito->FechaVencimiento);
                
                // Desplazar el vencimiento al siguiente día hábil si cae en un día no laborable (domingos, feriados, etc.)
                while (in_array($vencimientoEfectivo->toDateString(), $fechasNoMorosas)) {
                    $vencimientoEfectivo->addDay();
                }

                // El cliente tiene todo el día del "Vencimiento Efectivo" para pagar. 
                // Por lo tanto, SOLO se cobra mora si la fecha de apertura ($fecha) es ESTRICTAMENTE MAYOR al Vencimiento Efectivo.
                if ($fecha->toDateString() <= $vencimientoEfectivo->toDateString()) {
                    \Log::debug('[JOB] Crédito ' . $credito->CreditoID . ': En periodo de gracia. Vencimiento efectivo: ' . $vencimientoEfectivo->toDateString());
                    continue; // Aún no corresponde cobrar mora
                }
                // ---------------------------------------

                // Verificar si ya existe mora registrada para la fecha especificada
                $moraHoy = Mora::where('CreditoID', $credito->CreditoID)
                    ->whereDate('FechaMora', $fecha->toDateString())
                    ->exists();

                if ($moraHoy) {
                    \Log::debug('[JOB] Crédito ' . $credito->CreditoID . ': Ya tiene mora registrada para ' . $fecha->toDateString());
                    continue; // Ya se calculó para esa fecha
                }

                // Obtener saldo pendiente usando la fuente única de verdad (suma cuotas - suma pagos)
                $saldoPendiente = $credito->proposicion
                    ? ProposicionCredito::calcularSaldoPendiente($credito->proposicion->ProposicionCreditoID)
                    : 0;

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
                    'SedeID' => $credito->SedeID,
                ]);

                $morasCreadas++;

                // Log para auditoría
                \Log::info('[JOB] Mora calculada', [
                    'CreditoID' => $credito->CreditoID,
                    'ClienteID' => $cliente->ClienteID,
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
