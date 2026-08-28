<?php

namespace App\Jobs;

use App\Models\Credito;
use App\Services\MoraCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class CalcularMoraAutomatica implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $fecha;

    public function __construct($fecha = null)
    {
        $this->fecha = $fecha ? \Carbon\Carbon::parse($fecha)->toDateString() : today()->toDateString();
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('calcular-mora-automatica'))
                ->dontRelease()
                ->expireAfter(600),
        ];
    }

    public function handle(MoraCalculationService $moraService): void
    {
        set_time_limit(300);

        $fecha = $this->fecha ? \Carbon\Carbon::parse($this->fecha) : today();
        \Log::info('[JOB] CalcularMoraAutomatica: iniciando calculo de moras', ['fecha' => $fecha->toDateString()]);

        try {
            $morasCreadas = 0;

            Credito::withoutGlobalScope('sede')
                ->where('Activo', 1)
                ->whereDate('FechaVencimiento', '<=', $fecha)
                ->whereHas('proposicion', fn ($query) => $query->where('SaldoPendiente', '>', 0))
                ->whereIn('SedeID', \App\Models\Sede::where('Activo', true)->pluck('SedeID'))
                ->chunkById(500, function ($creditos) use ($fecha, $moraService, &$morasCreadas) {
                    foreach ($creditos as $credito) {
                        $resultado = $moraService->procesarCreditoHasta($credito, $fecha);
                        $morasCreadas += $resultado['creadas'];
                    }
                }, 'CreditoID');

            \Log::info('[JOB] CalcularMoraAutomatica completado. Moras creadas: '.$morasCreadas, ['fecha' => $fecha->toDateString()]);
        } catch (\Exception $e) {
            \Log::error('[JOB] Error en CalcularMoraAutomatica', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
