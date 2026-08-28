<?php

namespace App\Listeners;

use App\Jobs\CalcularMoraAutomatica;
use App\Models\Sede;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;

/**
 * CalcularMoraAlAbrirDia
 *
 * Se ejecuta automáticamente cuando se abre un nuevo día en el sistema.
 * Calcula la mora para todos los créditos vencidos.
 */
class CalcularMoraAlAbrirDia implements ShouldQueueAfterCommit
{
    public $connection = 'background';

    public function handle($event)
    {
        set_time_limit(300);

        Log::info('[LISTENER] CalcularMoraAlAbrirDia disparado', [
            'event' => get_class($event),
            'fecha' => $event?->aperturaCierre?->Fecha ?? 'desconocida',
        ]);

        // El listener corre en segundo plano, después de confirmar la apertura.
        try {
            $fecha = $event->aperturaCierre?->Fecha;
            $sedeId = (int) ($event->aperturaCierre?->SedeID ?? 0);
            $sede = $sedeId > 0 ? Sede::find($sedeId) : null;

            if (! $sede) {
                Log::warning('[LISTENER] No se calculo mora: apertura sin sede valida', [
                    'fecha' => $fecha,
                    'sede_id' => $sedeId,
                ]);

                return;
            }

            if (str_contains(mb_strtolower($sede->Nombre), 'gerencia')) {
                Log::info('[LISTENER] Calculo de mora omitido para Gerencia', [
                    'fecha' => $fecha,
                    'sede_id' => $sedeId,
                ]);

                return;
            }

            Log::info('[LISTENER] Despachando CalcularMoraAutomatica por sede', [
                'fecha' => $fecha,
                'sede_id' => $sedeId,
                'sede' => $sede->Nombre,
            ]);
            // El trabajo pesado se ejecuta después de enviar la respuesta al usuario.
            CalcularMoraAutomatica::dispatchSync($fecha, $sedeId);
            Log::info('[LISTENER] CalcularMoraAutomatica ejecutado correctamente');
        } catch (\Exception $e) {
            Log::error('[LISTENER] Error en CalcularMoraAutomatica', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
