<?php

namespace App\Jobs;

use App\Models\Credito;
use App\Models\Sede;
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
    public ?int $sedeId;

    public function __construct($fecha = null, ?int $sedeId = null)
    {
        $this->fecha = $fecha ? \Carbon\Carbon::parse($fecha)->toDateString() : today()->toDateString();
        $this->sedeId = $sedeId;
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('calcular-mora-automatica:sede:'.($this->sedeId ?? 'todas')))
                ->dontRelease()
                ->expireAfter(600),
        ];
    }

    public function handle(MoraCalculationService $moraService): void
    {
        set_time_limit(300);

        $fecha = $this->fecha ? \Carbon\Carbon::parse($this->fecha) : today();
        \Log::info('[JOB] CalcularMoraAutomatica: iniciando calculo de moras', [
            'fecha' => $fecha->toDateString(),
            'sede_id' => $this->sedeId,
        ]);

        try {
            $morasCreadas = 0;

            if ($this->sedeId !== null) {
                $sede = Sede::find($this->sedeId);

                if (! $sede || ! $sede->Activo || str_contains(mb_strtolower($sede->Nombre), 'gerencia')) {
                    \Log::info('[JOB] Calculo de mora omitido para sede no operativa', [
                        'fecha' => $fecha->toDateString(),
                        'sede_id' => $this->sedeId,
                    ]);

                    return;
                }
            }

            $query = Credito::withoutGlobalScope('sede')
                ->where('Activo', 1)
                ->whereDate('FechaVencimiento', '<=', $fecha)
                ->whereHas('proposicion', fn ($query) => $query->where('SaldoPendiente', '>', 0));

            if ($this->sedeId !== null) {
                $query->where('SedeID', $this->sedeId);
            } else {
                $sedesOperativas = Sede::where('Activo', true)
                    ->whereRaw('LOWER(Nombre) NOT LIKE ?', ['%gerencia%'])
                    ->pluck('SedeID');

                $query->whereIn('SedeID', $sedesOperativas);
            }

            $query->chunkById(500, function ($creditos) use ($fecha, $moraService, &$morasCreadas) {
                    foreach ($creditos as $credito) {
                        $resultado = $moraService->procesarCreditoHasta($credito, $fecha);
                        $morasCreadas += $resultado['creadas'];
                    }
                }, 'CreditoID');

            \Log::info('[JOB] CalcularMoraAutomatica completado. Moras creadas: '.$morasCreadas, [
                'fecha' => $fecha->toDateString(),
                'sede_id' => $this->sedeId,
            ]);
        } catch (\Exception $e) {
            \Log::error('[JOB] Error en CalcularMoraAutomatica', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
